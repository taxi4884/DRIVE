<?php
// Prüfen, ob ein Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    // Wenn nicht eingeloggt, Umleitung zur Login-Seite
    header("Location: login.php");
    exit;
}

try {
    // Aktuell eingeloggter Benutzer
    $userId = $_SESSION['user_id'];

    // Primärrolle, Sekundarrolle und Benutzername abrufen
    $stmt = $pdo->prepare("SELECT Rolle, SekundarRolle, Name FROM Benutzer WHERE BenutzerID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Primärrolle in der Session speichern
    if ($user && isset($user['Rolle'])) {
        $_SESSION['rolle'] = $user['Rolle'];
    } else {
        $_SESSION['rolle'] = 'Keine'; // Fallback, falls keine Rolle gesetzt ist
    }

    // Sekundarrolle setzen
    $sekundarRolle = $user['SekundarRolle'] ?? 'Keine'; // Standardwert, wenn keine Sekundarrolle vorhanden

    // Name in der Session speichern
    if ($user && isset($user['Name'])) {
        $_SESSION['user_name'] = $user['Name'];
    } else {
        $_SESSION['user_name'] = 'Gast'; // Fallback, falls kein Name vorhanden ist
    }

} catch (PDOException $e) {
    die("Fehler beim Abrufen der Benutzerdaten: " . $e->getMessage());
}
?>
