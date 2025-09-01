<?php
// send_urlaub_anfragen.php

// Fehleranzeige deaktivieren (für Produktion)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// UTF-8 sicherstellen
header('Content-Type: text/plain; charset=utf-8');

// Funktion zum Protokollieren von Nachrichten (für Cronjobs geeignet)
require_once __DIR__ . '/../../includes/logger.php';
$logFile = __DIR__ . '/send_urlaub_anfragen.log';

// Log-Funktion initialisieren
logMessage("Skript gestartet.", $logFile);

// Lock-Datei erstellen, um parallele Ausführung zu verhindern
$lockFile = '/tmp/send_urlaub_anfragen.lock';
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
$dbPath = __DIR__ . '/../../includes/db_connection.php';
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

// PHPMailer-Klassen einbinden
$phpmailerPath = __DIR__ . '/../../phpmailer/';
$exceptionPath = $phpmailerPath . 'Exception.php';
$phpmailerClassPath = $phpmailerPath . 'PHPMailer.php';
$smtpPath = $phpmailerPath . 'SMTP.php';

if (file_exists($exceptionPath) && file_exists($phpmailerClassPath) && file_exists($smtpPath)) {
    require_once $exceptionPath;
    require_once $phpmailerClassPath;
    require_once $smtpPath;
    logMessage("PHPMailer-Klassen eingebunden.", $logFile);
} else {
    logMessage("Fehler: PHPMailer-Klassen nicht gefunden. Prüfe die Pfade.", $logFile);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Funktion zum Senden von E-Mails
function sendeEmail($empfaengerEmail, $empfaengerName, $subject, $body) {
    logMessage("Senden einer E-Mail an: {$empfaengerEmail}", $logFile);
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($empfaengerEmail, $empfaengerName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        logMessage("E-Mail erfolgreich gesendet an: {$empfaengerEmail}", $logFile);
        return true;
    } catch (Exception $e) {
        logMessage("Fehler beim Senden der E-Mail an {$empfaengerEmail}: {$mail->ErrorInfo}", $logFile);
        return false;
    }
}

// Hauptprozess
try {
    logMessage("Starte Hauptprozess für Urlaubsanfragen.", $logFile);
    
    // Abrufen aller beantragten Urlaube, die noch nicht verarbeitet wurden
    $query = "
        SELECT * 
        FROM FahrerAbwesenheiten 
        WHERE abwesenheitsart = 'Urlaub' 
          AND status = 'beantragt'
          AND processed = 0
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $antraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMessage("Anzahl der unprocessed Urlaubsanträge: " . count($antraege), $logFile);

    foreach ($antraege as $antrag) {
        // Definieren Sie den Empfänger, Betreff und Inhalt der E-Mail
        // Hier ein Beispiel; passen Sie es nach Bedarf an.
        $to = 'verantwortlicher@example.com';  // Setzen Sie die reale Empfängeradresse ein
        $subject = "Neuer Urlaubsantrag von Fahrer ID: " . $antrag['FahrerID'];
        $body = "
            <p>Es liegt ein neuer Urlaubsantrag vor:</p>
            <p>
              FahrerID: " . htmlspecialchars($antrag['FahrerID']) . "<br>
              Zeitraum: " . htmlspecialchars($antrag['startdatum']) . " bis " . htmlspecialchars($antrag['enddatum']) . "<br>
              Kommentar: " . nl2br(htmlspecialchars($antrag['kommentar'])) . "
            </p>
        ";

        // E-Mail senden
        if (sendeEmail($to, 'Urlaubsverwalter', $subject, $body)) {
            // Aktualisieren des processed-Status nach erfolgreich versendeter E-Mail
            $updateStmt = $pdo->prepare("
                UPDATE FahrerAbwesenheiten 
                SET processed = 1 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $antrag['id']]);
            logMessage("Antrag ID " . $antrag['id'] . " als processed markiert.", $logFile);
        }
    }
} catch (Exception $e) {
    logMessage("Fehler im Hauptprozess: " . $e->getMessage(), $logFile);
}

logMessage("Skript beendet.", $logFile);
?>
