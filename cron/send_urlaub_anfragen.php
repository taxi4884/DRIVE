<?php
// send_urlaub_anfragen.php

// Fehleranzeige deaktivieren (für Produktion)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// UTF-8 sicherstellen
header('Content-Type: text/plain; charset=utf-8');

// Funktion zum Protokollieren von Nachrichten (für Cronjobs geeignet)
require_once __DIR__ . '/../includes/logger.php';
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
$dbPath = __DIR__ . '/../includes/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    logMessage("Datenbankverbindung eingebunden.", $logFile);
} else {
    logMessage("Fehler: Datenbankverbindungsdatei nicht gefunden: {$dbPath}", $logFile);
    exit;
}

// Konfigurationsdatei einbinden
$configPath = __DIR__ . '/../includes/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    logMessage("Konfigurationsdatei eingebunden.", $logFile);
} else {
    logMessage("Fehler: Konfigurationsdatei nicht gefunden: {$configPath}", $logFile);
    exit;
}

require_once __DIR__ . '/../includes/mailer.php';

// Abrufen aller beantragten Urlaube, die noch nicht verarbeitet wurden
try {
    logMessage("Abrufen unprocessed Urlaubsanträge.", $logFile);
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
    logMessage("Anzahl unprocessed Urlaubsanträge: " . count($antraege), $logFile);
} catch (PDOException $e) {
    logMessage("Fehler beim Abrufen der Urlaubsanträge: " . $e->getMessage(), $logFile);
    exit;
}


// Abrufen aller Benutzer, bei denen KrankFahrer = 1
try {
    logMessage("Abrufen der E-Mail-Adressen für Benutzer mit KrankFahrer = 1.", $logFile);
    $empfaengerStmt = $pdo->prepare("
        SELECT Email, Name 
        FROM Benutzer 
        WHERE KrankFahrer = 1
    ");
    $empfaengerStmt->execute();
    $empfaengerListe = $empfaengerStmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage("Anzahl Empfänger: " . count($empfaengerListe), $logFile);
} catch (PDOException $e) {
    logMessage("Fehler beim Abrufen der Empfänger: " . $e->getMessage(), $logFile);
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
        sendEmail($empfaenger['Email'], $empfaenger['Name'], $subject, $body);
    }

    // Markiere den Antrag als processed
    try {
        $updateStmt = $pdo->prepare("
            UPDATE FahrerAbwesenheiten 
            SET processed = 1 
            WHERE id = :id
        ");
        $updateStmt->execute(['id' => $antrag['id']]);
        logMessage("Antrag ID " . $antrag['id'] . " als processed markiert.", $logFile);
    } catch (PDOException $e) {
        logMessage("Fehler beim Aktualisieren des Antrags ID " . $antrag['id'] . ": " . $e->getMessage(), $logFile);
    }
}

logMessage("Skript beendet.", $logFile);
?>
