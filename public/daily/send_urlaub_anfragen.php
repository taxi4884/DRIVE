<?php
// send_urlaub_anfragen.php

// Fehleranzeige deaktivieren (für Produktion)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// UTF-8 sicherstellen
header('Content-Type: text/plain; charset=utf-8');

// Funktion zum Protokollieren von Nachrichten (für Cronjobs geeignet)
function logMessage($message) {
    $logFile = __DIR__ . '/send_urlaub_anfragen.log';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "{$timestamp} {$message}\n", FILE_APPEND);
}

// Log-Funktion initialisieren
logMessage("Skript gestartet.");

// Lock-Datei erstellen, um parallele Ausführung zu verhindern
$lockFile = '/tmp/send_urlaub_anfragen.lock';
if (file_exists($lockFile)) {
    logMessage("Skript läuft bereits.");
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
    logMessage("Datenbankverbindung eingebunden.");
} else {
    logMessage("Fehler: Datenbankverbindungsdatei nicht gefunden: {$dbPath}");
    exit;
}

// Konfigurationsdatei einbinden
$configPath = __DIR__ . '/../../includes/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    logMessage("Konfigurationsdatei eingebunden.");
} else {
    logMessage("Fehler: Konfigurationsdatei nicht gefunden: {$configPath}");
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
    logMessage("PHPMailer-Klassen eingebunden.");
} else {
    logMessage("Fehler: PHPMailer-Klassen nicht gefunden. Prüfe die Pfade.");
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Funktion zum Senden von E-Mails
function sendeEmail($empfaengerEmail, $empfaengerName, $subject, $body) {
    logMessage("Senden einer E-Mail an: {$empfaengerEmail}");
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
        logMessage("E-Mail erfolgreich gesendet an: {$empfaengerEmail}");
        return true;
    } catch (Exception $e) {
        logMessage("Fehler beim Senden der E-Mail an {$empfaengerEmail}: {$mail->ErrorInfo}");
        return false;
    }
}

// Abrufen aller beantragten Urlaube, die noch nicht verarbeitet wurden
try {
    logMessage("Abrufen unprocessed Urlaubsanträge.");
    $query = "
        SELECT fa.*, f.Vorname, f.Nachname
        FROM FahrerAbwesenheiten fa
        JOIN Fahrer f ON fa.FahrerID = f.FahrerID
        WHERE fa.abwesenheitsart = 'Urlaub' 
          AND fa.status = 'beantragt'
          AND fa.processed = 0
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $antraege = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage("Anzahl unprocessed Urlaubsanträge: " . count($antraege));
} catch (PDOException $e) {
    logMessage("Fehler beim Abrufen der Urlaubsanträge: " . $e->getMessage());
    exit;
}


// Abrufen aller Benutzer, bei denen KrankFahrer = 1
try {
    logMessage("Abrufen der E-Mail-Adressen für Benutzer mit KrankFahrer = 1.");
    $empfaengerStmt = $pdo->prepare("
        SELECT Email, Name 
        FROM Benutzer 
        WHERE KrankFahrer = 1
    ");
    $empfaengerStmt->execute();
    $empfaengerListe = $empfaengerStmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage("Anzahl Empfänger: " . count($empfaengerListe));
} catch (PDOException $e) {
    logMessage("Fehler beim Abrufen der Empfänger: " . $e->getMessage());
    $empfaengerListe = [];
}

// Hauptprozess für jeden Urlaubsantrag
foreach ($antraege as $antrag) {
    // Verwende Vorname und Nachname im Betreff
    $subject = "Neuer Urlaubsantrag von " . htmlspecialchars($antrag['Vorname'] . ' ' . $antrag['Nachname']);
    
    // Passe den Nachrichtentext an
    $body = "
        <p>Es liegt ein neuer Urlaubsantrag vor:</p>
        <p>
          Fahrer: " . htmlspecialchars($antrag['Vorname'] . ' ' . $antrag['Nachname']) . "<br>
          Zeitraum: " . htmlspecialchars($antrag['startdatum']) . " bis " . htmlspecialchars($antrag['enddatum']) . "<br>
          Kommentar: " . nl2br(htmlspecialchars($antrag['kommentar'])) . "
        </p>
    ";

    // E-Mail an alle Empfänger senden
    foreach ($empfaengerListe as $empfaenger) {
        sendeEmail($empfaenger['Email'], $empfaenger['Name'], $subject, $body);
    }

    // Markiere den Antrag als processed
    try {
        $updateStmt = $pdo->prepare("
            UPDATE FahrerAbwesenheiten 
            SET processed = 1 
            WHERE id = :id
        ");
        $updateStmt->execute(['id' => $antrag['id']]);
        logMessage("Antrag ID " . $antrag['id'] . " als processed markiert.");
    } catch (PDOException $e) {
        logMessage("Fehler beim Aktualisieren des Antrags ID " . $antrag['id'] . ": " . $e->getMessage());
    }
}

logMessage("Skript beendet.");
?>
