<?php
// send_weekly_sales.php

// Produktion: Fehler nicht anzeigen, aber loggen
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Kein Header nötig im Cron-Kontext, aber konsistent lassen schadet nicht
if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

// --- Logging ---
require_once __DIR__ . '/../../includes/logger.php';
$logFile = __DIR__ . '/send_weekly_sales.log';
logMessage("Skript gestartet.", $logFile);

// --- Single-Instance Lock ---
$lockFile = '/tmp/send_weekly_sales.lock';
if (file_exists($lockFile)) {
    logMessage("Skript läuft bereits. Abbruch.", $logFile);
    exit;
}
file_put_contents($lockFile, (string)getmypid());
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) @unlink($lockFile);
});

// --- Includes ---
$dbPath = __DIR__ . '/../../includes/db.php';
if (!file_exists($dbPath)) { logMessage("DB include fehlt: {$dbPath}", $logFile); exit; }
require_once $dbPath;
logMessage("DB geladen.", $logFile);

$configPath = __DIR__ . '/../../includes/config.php';
if (!file_exists($configPath)) { logMessage("Config include fehlt: {$configPath}", $logFile); exit; }
require_once $configPath;
logMessage("Config geladen.", $logFile);

require_once __DIR__ . '/../../includes/mailer.php';

// --- Hilfsfunktionen Zeit / Format ---
/**
 * Liefert Start/Ende der Vorwoche (Mo 00:00:00 bis So 23:59:59) unabhängig vom Ausführungstag.
 * Erwartet Serverzeit in Europa/Berlin (Cron auf Mo ist trotzdem robust).
 */
function getLastWeekRange(): array {
    $tz = new DateTimeZone('Europe/Berlin');
    $now = new DateTime('now', $tz);

    // ISO: Woche beginnt Montag
    // "letzte Woche Montag"
    $start = new DateTime('monday last week 00:00:00', $tz);
    $end   = new DateTime('sunday last week 23:59:59', $tz);

    return [$start, $end];
}

function formatDE(DateTime $dt): string {
    return $dt->format('d.m.Y');
}

function kwLabel(DateTime $anyDayOfWeek): string {
    // ISO-KW
    return $anyDayOfWeek->format('W');
}

// --- Empfänger laden ---
function getEmailRecipients(PDO $pdo): array {
    logMessage("Hole Empfänger (UmsatzMails=1) …", $logFile);
    $stmt = $pdo->prepare("
        SELECT Email, Name
        FROM Benutzer
        WHERE UmsatzMails = 1 AND Email <> '' 
    ");
    if (!$stmt->execute()) {
        logMessage("Empfänger-Abfrage fehlgeschlagen.", $logFile);
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    logMessage("Empfänger: " . count($rows), $logFile);
    return $rows;
}

// --- Vorwochen-Report ---
/**
 * Liefert je Fahrer: Umsatz gesamt, Arbeitszeit (HH:MM:SS) und €/Std. für das Intervall.
 * Tabellen: Umsatz(Datum, FahrerID, TaxameterUmsatz, OhneTaxameter)
 *           Fahrer(FahrerID, vorname, nachname, fms_alias, Fahrernummer)
 *           sync_fahreranmeldung(fahrer, anmeldung, abmeldung)
 */
function getLastWeekSalesWithWorktime(PDO $pdo, DateTime $ws, DateTime $we): array {
    logMessage("Aggregiere Vorwoche: ".$ws->format('Y-m-d H:i:s')." – ".$we->format('Y-m-d H:i:s'), $logFile);

    $weekStart = $ws->format('Y-m-d H:i:s');
    $weekEnd   = $we->format('Y-m-d H:i:s');

    // Für Umsatz: Annahme, dass Umsatz.Datum als DATE (oder DATEPART von DATETIME) gespeichert ist.
    // Für Worktime: Overlap-Logik, offene Sessions via COALESCE(abmeldung, :we).
    $sql = "
      WITH umsaetze AS (
        SELECT 
          u.FahrerID,
          SUM(u.TaxameterUmsatz + u.OhneTaxameter) AS total_sales
        FROM Umsatz u
        WHERE u.Datum BETWEEN :ws_date AND :we_date
        GROUP BY u.FahrerID
      ),
      arbeitszeit AS (
        SELECT 
          f.FahrerID,
          -- Summe der überlappenden Sekunden im Intervall
          SUM(
            GREATEST(
              0,
              TIMESTAMPDIFF(
                SECOND,
                GREATEST(sf.anmeldung, :ws),
                LEAST(COALESCE(sf.abmeldung, :we), :we)
              )
            )
          ) AS work_seconds
        FROM Fahrer f
        LEFT JOIN sync_fahreranmeldung sf
          ON (sf.fahrer = f.fms_alias OR sf.fahrer = f.Fahrernummer)
         AND sf.anmeldung < :we
         AND COALESCE(sf.abmeldung, :we) > :ws
        GROUP BY f.FahrerID
      )
      SELECT
        f.FahrerID,
        f.vorname,
        f.nachname,
        COALESCE(u.total_sales, 0) AS total_sales,
        SEC_TO_TIME(COALESCE(a.work_seconds, 0)) AS total_work_time,
        ROUND(
          COALESCE(u.total_sales, 0) / GREATEST(COALESCE(a.work_seconds, 0) / 3600, 1),
          2
        ) AS sales_per_hour
      FROM Fahrer f
      LEFT JOIN umsaetze u  ON u.FahrerID = f.FahrerID
      LEFT JOIN arbeitszeit a ON a.FahrerID = f.FahrerID
      -- Optional: Nur Fahrer mit Umsatz ODER Arbeitszeit anzeigen
      WHERE COALESCE(u.total_sales, 0) > 0 OR COALESCE(a.work_seconds, 0) > 0
      ORDER BY total_sales DESC
    ";

    $stmt = $pdo->prepare($sql);
    $bindOk = $stmt->execute([
        ':ws_date' => $ws->format('Y-m-d'),
        ':we_date' => $we->format('Y-m-d'),
        ':ws'      => $weekStart,
        ':we'      => $weekEnd,
    ]);
    if (!$bindOk) {
        $err = $stmt->errorInfo();
        throw new Exception("DB-Fehler bei Vorwochen-Query: " . implode(' | ', $err));
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// --- CSV bauen ---
function buildCsv(array $rows): string {
    $fh = fopen('php://temp', 'r+');

    // BOM hinzufügen, damit Excel korrekt als UTF-8 erkennt
    fwrite($fh, "\xEF\xBB\xBF");

    fputcsv($fh, ['FahrerID','Vorname','Nachname','Umsatz_EUR','Arbeitszeit_HH:MM:SS','EUR_pro_Stunde'], ';');
    foreach ($rows as $r) {
        fputcsv($fh, [
            $r['FahrerID'],
            $r['vorname'],
            $r['nachname'],
            number_format((float)$r['total_sales'], 2, ',', ''),
            $r['total_work_time'],
            number_format((float)$r['sales_per_hour'], 2, ',', '')
        ], ';');
    }
    rewind($fh);
    return stream_get_contents($fh);
}

// --- Email senden ---
// --- Haupt ---
try {
    logMessage("Starte Hauptlauf …", $logFile);

    [$ws, $we] = getLastWeekRange();
    // Für Betreff/KW: eine Datum innerhalb der Vorwoche
    $mid = clone $ws; $mid->modify('+3 days'); // ungefähr Wochenmitte
    $kw  = kwLabel($mid);

    $data = getLastWeekSalesWithWorktime($pdo, $ws, $we);

    $totalSales = 0.0;
    foreach ($data as $r) { $totalSales += (float)$r['total_sales']; }

    $recipients = getEmailRecipients($pdo);

    $rangeLabel = formatDE($ws) . '–' . formatDE($we);
    $subject = "Umsatz & Arbeitszeit – KW {$kw} ({$rangeLabel})";

    // CSV erzeugen (einmal)
    $csv = buildCsv($data);
    $csvFilename = "report_kw{$kw}_{$ws->format('Ymd')}-{$we->format('Ymd')}.csv";

    foreach ($recipients as $rcpt) {
        $emailBody = "
        <div style='font-family:Arial,sans-serif;color:#333;font-size:14px;max-width:720px;margin:auto;'>
          <h2 style='color:#2E86C1;margin:0 0 8px;font-size:18px;'>
            Umsatz & Arbeitszeit – KW {$kw} ({$rangeLabel})
          </h2>
          <p style='margin:6px 0 14px;'>Hallo ".htmlspecialchars($rcpt['Name']).",</p>
          <p style='margin:0 0 12px;'>
            <strong>Gesamtsumme Umsatz:</strong> ".number_format($totalSales,2,',','.')." €
          </p>

          <table style='width:100%;border:1px solid #e0e0e0;border-collapse:separate;border-spacing:0;border-radius:6px;overflow:hidden;box-shadow:0 2px 5px rgba(0,0,0,0.05);'>
            <thead>
              <tr style='background:#f9f9f9;'>
                <th style='padding:6px 8px;text-align:left;'>Fahrer</th>
                <th style='padding:6px 8px;text-align:right;'>Umsatz (€)</th>
                <th style='padding:6px 8px;text-align:center;'>Arbeitszeit</th>
                <th style='padding:6px 8px;text-align:right;'>€/Std.</th>
              </tr>
            </thead>
            <tbody>
        ";

        $toggle = false;
        foreach ($data as $row) {
            $bg   = $toggle ? '#ffffff' : '#fcfcfc'; $toggle = !$toggle;
            $name = htmlspecialchars(($row['vorname'] ?? '').' '.($row['nachname'] ?? ''));
            $emailBody .= "
              <tr style='background:{$bg};'>
                <td style='padding:6px 8px;border-bottom:1px solid #e0e0e0;'>{$name}</td>
                <td style='padding:6px 8px;text-align:right;border-bottom:1px solid #e0e0e0;'>".number_format($row['total_sales'],2,',','.')."</td>
                <td style='padding:6px 8px;text-align:center;border-bottom:1px solid #e0e0e0;'>".$row['total_work_time']."</td>
                <td style='padding:6px 8px;text-align:right;border-bottom:1px solid #e0e0e0;'>".number_format($row['sales_per_hour'],2,',','.')."</td>
              </tr>
            ";
        }

        $emailBody .= "
            </tbody>
          </table>

          <p style='margin:14px 0 8px;'>Die vollständigen Daten findest du im Anhang als CSV.</p>
          <p style='margin:10px 0 0;'>Viele Grüße<br><strong>Dein System 🚀</strong></p>
        </div>
        ";

        sendEmail(
            $rcpt['Email'],
            $rcpt['Name'],
            $subject,
            $emailBody,
            [
                ['string' => $csv, 'filename' => $csvFilename, 'type' => 'text/csv']
            ]
        );
    }

    logMessage("Mails versendet. Empfänger: ".count($recipients).", Fahrerzeilen: ".count($data), $logFile);
} catch (Throwable $e) {
    logMessage("FATAL: ".$e->getMessage(), $logFile);
    // Absichtlich kein rethrow – Cron soll nicht explodieren
}

logMessage("Skript beendet.", $logFile);
