<?php
// index.php
header("Content-Type: application/xml; charset=utf-8");

// Definieren Sie den Dateinamen für die Nachrichten-Queue
$filename = "message.txt";

// Unterscheidung der HTTP-Methode
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- XML-Verarbeitung (POST) ---
    $xmlContent = file_get_contents('php://input');
    if (!$xmlContent) {
        http_response_code(400);
        echo "<response><status>Error</status><message>Kein XML-Inhalt empfangen.</message></response>";
        exit;
    }
    
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$doc->loadXML($xmlContent)) {
        libxml_clear_errors();
        http_response_code(400);
        echo "<response><status>Error</status><message>Ungültiges XML.</message></response>";
        exit;
    }
    
    // Hier können Sie Ihre spezifische XML-Verarbeitungslogik einbinden.
    // Für das Beispiel simulieren wir die Verarbeitung mit einer Erfolgsmeldung.
    $message = "XML wurde erfolgreich verarbeitet am " . date("c");
    
    // Speichern der Nachricht in der Datei (Queue-Simulation)
    file_put_contents($filename, $message);
    
    // Senden einer XML-Antwort
    echo "<response>
            <status>OK</status>
            <message>XML wurde erfolgreich verarbeitet.</message>
          </response>";
          
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Long Polling (GET) ---
    $timeout = 30; // Maximale Wartezeit in Sekunden
    $interval = 1; // Prüfintervall in Sekunden
    $elapsed = 0;
    $foundMessage = false;
    $message = "";
    
    // Long-Polling-Schleife: Warte bis zu $timeout Sekunden auf eine Nachricht
    while ($elapsed < $timeout) {
        if (file_exists($filename)) {
            $message = file_get_contents($filename);
            // Löschen der Datei, damit die Nachricht nur einmal abgerufen wird
            unlink($filename);
            $foundMessage = true;
            break;
        }
        sleep($interval);
        $elapsed += $interval;
    }
    
    // Senden der Antwort basierend auf dem Ergebnis
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
    
} else {
    // Falls eine andere HTTP-Methode verwendet wird
    http_response_code(405);
    echo "<response><status>Error</status><message>Ungültige Anfrage-Methode</message></response>";
}
?>
