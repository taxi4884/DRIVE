<?php
// longpoll.php
header("Content-Type: application/xml; charset=utf-8");

// Konfiguration für Long Polling
$timeout = 30; // Maximale Wartezeit in Sekunden
$interval = 1; // Prüfintervall in Sekunden
$elapsed = 0;
$foundMessage = false;
$message = "";

// Long Polling-Schleife: Warte bis zu $timeout Sekunden auf eine Nachricht
while ($elapsed < $timeout) {
    if (file_exists("message.txt")) {
        $message = file_get_contents("message.txt");
        // Entferne die Datei nach dem Lesen, um die Nachricht als einmalig zu behandeln
        unlink("message.txt");
        $foundMessage = true;
        break;
    }
    sleep($interval);
    $elapsed += $interval;
}

// Sende die entsprechende XML-Antwort
if ($foundMessage) {
    echo "<longpoll>
            <status>OK</status>
            <message>" . htmlspecialchars($message) . "</message>
          </longpoll>";
} else {
    echo "<longpoll>
            <status>timeout</status>
            <message>Keine neuen Daten verfügbar.</message>
          </longpoll>";
}
?>
