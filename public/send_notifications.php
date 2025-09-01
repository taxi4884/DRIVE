<?php
// Pushover-Konfiguration
define('PUSHOVER_API_TOKEN', 'arcibowg5bdmtojbojitf9abgnjxgm'); // Dein Pushover API-Token
define('PUSHOVER_USER_KEY', 'uv39jmw3gj7x1iyxn7j8n8euc618he');  // Dein Pushover User-Key

// Datenbankverbindung einbinden
require_once '../includes/db.php'; // `db.php` stellt die $pdo-Verbindung bereit

// Abfrage neuer Benachrichtigungen
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE gesendet = 0");
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Vorname, Nachname und Umsatz auslesen
        $vorname = $row['vorname'];
        $nachname = $row['nachname'];
        $umsatz = $row['umsatz'];

        // Nachricht für Pushover erstellen
        $message = "Neue Umsatzmeldung von $vorname $nachname\nUmsatz: " . number_format($umsatz, 2) . " €";
        sendPushoverNotification($message);

        // Benachrichtigung als gesendet markieren
        $updateStmt = $pdo->prepare("UPDATE notifications SET gesendet = 1 WHERE id = ?");
        $updateStmt->execute([$row['id']]);
    }
} catch (PDOException $e) {
    die("Fehler bei der SQL-Abfrage: " . $e->getMessage());
}

// Funktion zum Senden von Pushover-Benachrichtigungen
function sendPushoverNotification($message) {
    $data = [
        "token" => PUSHOVER_API_TOKEN,
        "user" => PUSHOVER_USER_KEY,
        "message" => $message,
        "title" => "Neuer Umsatz-Eintrag",
        "priority" => 1
    ];

    $ch = curl_init("https://api.pushover.net/1/messages.json");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        die("cURL-Fehler: " . curl_error($ch));
    }

    curl_close($ch);

    if ($response) {
        echo "Benachrichtigung erfolgreich gesendet: $message\n";
    } else {
        echo "Fehler beim Senden der Benachrichtigung.\n";
    }
}
?>
