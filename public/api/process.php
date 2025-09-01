<?php
// process.php
header("Content-Type: application/xml; charset=utf-8");

// Lese den XML-Inhalt aus dem Request-Body
$xmlContent = file_get_contents('php://input');
if (!$xmlContent) {
    http_response_code(400);
    echo "<response><status>Error</status><message>Kein XML-Inhalt empfangen.</message></response>";
    exit;
}

// Versuche, das XML zu laden
$doc = new DOMDocument();
libxml_use_internal_errors(true);
if (!$doc->loadXML($xmlContent)) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    http_response_code(400);
    echo "<response><status>Error</status><message>Ungültiges XML.</message></response>";
    exit;
}

// Hier können Sie Ihre bestehende Logik zur XML-Verarbeitung einbinden.
// Für dieses Beispiel simulieren wir die Verarbeitung mit einer einfachen Nachricht.
$message = "XML wurde erfolgreich verarbeitet am " . date("c");

// Speichern der Nachricht in einer Datei (als einfache Queue-Simulation)
file_put_contents("message.txt", $message);

// Sende eine XML-Antwort zurück
echo "<response>
        <status>OK</status>
        <message>XML wurde erfolgreich verarbeitet.</message>
      </response>";
?>
