<?php
// send_daily_sales.php

// Fehleranzeige deaktivieren (für Produktion)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// UTF-8 sicherstellen
header('Content-Type: text/plain; charset=utf-8');

// Funktion zum Protokollieren von Nachrichten (für Cronjobs geeignet)
require_once __DIR__ . '/../../includes/logger.php';
$logFile = __DIR__ . '/send_daily_sales.log';

logMessage("Skript gestartet.", $logFile);

// Lock-Datei erstellen, um parallele Ausführung zu verhindern
$lockFile = '/tmp/send_daily_sales.lock';
if (file_exists($lockFile)) {
    logMessage("Skript läuft bereits.", $logFile);
    exit;
}
file_put_contents($lockFile, 'locked');
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

// Datenbankverbindung einbinden
$dbPath = __DIR__ . '/../../includes/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    logMessage("Datenbankverbindung eingebunden.", $logFile);
} else {
    logMessage("Fehler: Datenbankverbindungsdatei nicht gefunden: {$dbPath}", $logFile);
    exit;
}

// Konfigurationsdatei einbinden
$configPath = __DIR__ . '/../../includes/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    logMessage("Konfigurationsdatei eingebunden.", $logFile);
} else {
    logMessage("Fehler: Konfigurationsdatei nicht gefunden: {$configPath}", $logFile);
    exit;
}

require_once __DIR__ . '/../../includes/mailer.php';

// Funktion zum Abrufen der E-Mail-Empfänger
function getEmailRecipients(PDO $pdo) {
    logMessage("Abrufen der E-Mail-Adressen für Benutzer mit UmsatzMails = 1.", $logFile);
    $stmt = $pdo->prepare("
        SELECT Email, Name
        FROM Benutzer
        WHERE UmsatzMails = 1
    ");
    if ($stmt->execute()) {
        logMessage("E-Mail-Empfänger erfolgreich abgerufen.", $logFile);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    logMessage("Fehler: E-Mail-Empfänger konnten nicht abgerufen werden.", $logFile);
    return [];
}

// Funktion zum Abrufen der Umsätze und Arbeitszeiten des Vortages (mit Sub-Query zur Aggregation)
function getYesterdaySalesWithWorktime(PDO $pdo) {
    logMessage("Abrufen der Umsätze und Arbeitszeiten des Vortages (mit Unter-Query).", $logFile);
    $yesterdayDate  = date('Y-m-d', strtotime('yesterday'));
    $yesterdayStart = "{$yesterdayDate} 00:00:00";
    $yesterdayEnd   = "{$yesterdayDate} 23:59:59";

    $sql = "
      SELECT
        f.FahrerID,
        f.vorname,
        f.nachname,
        us.total_sales,
        SEC_TO_TIME(
          SUM(
            TIMESTAMPDIFF(
              SECOND,
              GREATEST(sf.anmeldung, :ys),
              LEAST(sf.abmeldung, :ye)
            )
          )
        ) AS total_work_time,
        ROUND(
          us.total_sales
          / GREATEST(
              SUM(
                TIMESTAMPDIFF(
                  SECOND,
                  GREATEST(sf.anmeldung, :ys),
                  LEAST(sf.abmeldung, :ye)
                )
              ) / 3600
            , 1)
        , 2) AS sales_per_hour
      FROM (
        SELECT FahrerID, SUM(TaxameterUmsatz + OhneTaxameter) AS total_sales
        FROM Umsatz
        WHERE Datum = :yd
        GROUP BY FahrerID
      ) AS us
      JOIN Fahrer f ON us.FahrerID = f.FahrerID
      LEFT JOIN sync_fahreranmeldung sf
        ON (sf.fahrer = f.fms_alias OR sf.fahrer = f.Fahrernummer)
       AND sf.anmeldung < :ye
       AND sf.abmeldung > :ys
      GROUP BY f.FahrerID
      ORDER BY us.total_sales DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':yd' => $yesterdayDate,
        ':ys' => $yesterdayStart,
        ':ye' => $yesterdayEnd,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Funktion zum Senden von E-Mails
// Hauptprozess
try {
    logMessage("Starte den Hauptprozess.", $logFile);
    $yesterdayData = getYesterdaySalesWithWorktime($pdo);

    // Gesamten Umsatz aus den gruppierten Daten ermitteln
    $totalSales = array_sum(array_column($yesterdayData, 'total_sales'));

    $recipients = getEmailRecipients($pdo);
    $dateLabel  = date('d.m.Y', strtotime('yesterday'));

    foreach ($recipients as $recipient) {
        // Kompakteres, zentriertes Layout mit reduzierter Schriftgröße und Padding
        $emailBody = "
        <div style=\"
            font-family: Arial, sans-serif;
            color: #333;
            font-size: 14px;
            max-width: 600px;
            margin: auto;
        \">
          <h2 style=\"
              color: #2E86C1;
              margin: 0 0 8px;
              font-size: 18px;
          \">Umsatz & Arbeitszeit — {$dateLabel}</h2>
          <p style=\"margin: 4px 0 12px;\">Hallo " . htmlspecialchars($recipient['Name']) . ",</p>
          <p style=\"margin: 0 0 16px;\">
            <strong>Gesamtsumme:</strong>
            " . number_format($totalSales, 2, ',', '.') . " €
          </p>

          <table style=\"
              width: 100%;
              border: 1px solid #e0e0e0;
              border-collapse: separate;
              border-spacing: 0;
              border-radius: 6px;
              overflow: hidden;
              box-shadow: 0 2px 5px rgba(0,0,0,0.05);
          \">
            <thead>
              <tr style=\"background-color: #f9f9f9;\">
                <th style=\"padding: 6px 8px; text-align: left;\">Fahrer</th>
                <th style=\"padding: 6px 8px; text-align: right;\">Umsatz (€)</th>
                <th style=\"padding: 6px 8px; text-align: center;\">Arbeitszeit</th>
                <th style=\"padding: 6px 8px; text-align: right;\">€/Std.</th>
              </tr>
            </thead>
            <tbody>
        ";

        $toggle = false;
        foreach ($yesterdayData as $row) {
            $bg      = $toggle ? '#ffffff' : '#fcfcfc';
            $toggle = !$toggle;
            $emailBody .= "
              <tr style=\"background-color: {$bg};\">
                <td style=\"padding: 6px 8px; border-bottom: 1px solid #e0e0e0;\">
                  " . htmlspecialchars($row['vorname'] . ' ' . $row['nachname']) . "
                </td>
                <td style=\"padding: 6px 8px; text-align: right; border-bottom: 1px solid #e0e0e0;\">
                  " . number_format($row['total_sales'], 2, ',', '.') . "
                </td>
                <td style=\"padding: 6px 8px; text-align: center; border-bottom: 1px solid #e0e0e0;\">
                  " . $row['total_work_time'] . "
                </td>
                <td style=\"padding: 6px 8px; text-align: right; border-bottom: 1px solid #e0e0e0;\">
                  " . number_format($row['sales_per_hour'], 2, ',', '.') . "
                </td>
              </tr>
            ";
        }

        $emailBody .= "
            </tbody>
          </table>

          <p style=\"margin: 16px 0 0; font-size: 14px;\">
            Viele Grüße,<br>
            <strong>Dein System 🚀</strong>
          </p>
        </div>
        ";

        sendEmail(
            $recipient['Email'],
            $recipient['Name'],
            'Umsatz- & Arbeitszeitbericht — ' . $dateLabel,
            $emailBody
        );
    }

} catch (Exception $e) {
    logMessage("Fehler: " . $e->getMessage(), $logFile);
}

logMessage("Skript beendet.", $logFile);
