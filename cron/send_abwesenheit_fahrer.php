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
        if (sendEmail($to, 'Urlaubsverwalter', $subject, $body)) {
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
