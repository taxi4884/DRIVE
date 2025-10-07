<?php
require_once __DIR__ . '/cache.php';

// Prüfen, ob ein Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    // Wenn nicht eingeloggt, Umleitung zur Login-Seite
    header("Location: login.php");
    exit;
}

try {
    // Aktuell eingeloggter Benutzer
    $userId = (int) $_SESSION['user_id'];

    // Primärrolle, Sekundarrolle und Benutzername aus dem Cache abrufen
    $cacheKey = 'user_profile_' . $userId;
    $user = Cache::remember($cacheKey, function () use ($pdo, $userId) {
        $stmt = $pdo->prepare("SELECT Rolle, SekundarRolle, Name FROM Benutzer WHERE BenutzerID = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }, 600); // 10 Minuten Cache

    // Primärrolle in der Session speichern, normalisiert
    $_SESSION['rolle'] = ucfirst(strtolower($user['Rolle'] ?? ''));

    // Sekundärrollen normalisieren und als Array bereitstellen
    $sekundarRolle = array_map(
        fn($r) => ucfirst(strtolower($r)),
        array_filter(array_map('trim', explode(',', $user['SekundarRolle'] ?? '')))
    );

    $_SESSION['sekundarRolle'] = $sekundarRolle;

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
