<?php
require_once '../../includes/head.php'; // Lädt Authentifizierung und Datenbankverbindung

// Überprüfung, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    die('Fehler: Keine gültige Session. Bitte erneut anmelden.');
}

// Überprüfung, ob das Datum übergeben wurde
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['datum'])) {
    $datum = $_GET['datum'];
    $fahrer_id = $_SESSION['user_id']; // Fahrer-ID aus der Session

    // Validierung des Datums
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) { // Prüft auf das Format YYYY-MM-DD
        die('Ungültiges Datumsformat.');
    }

    // Überprüfung, ob der Eintrag existiert und zur Fahrer-ID gehört
    $stmt = $pdo->prepare("
        SELECT * 
        FROM Umsatz 
        WHERE Datum = ? AND FahrerID = ?
    ");
    $stmt->execute([$datum, $fahrer_id]);
    $eintrag = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eintrag) {
        die('Eintrag nicht gefunden oder keine Berechtigung, diesen Eintrag zu löschen.');
    }

    // Eintrag löschen
    try {
        $stmt = $pdo->prepare("
            DELETE FROM Umsatz 
            WHERE Datum = ? AND FahrerID = ?
        ");
        $stmt->execute([$datum, $fahrer_id]);

        // Weiterleitung nach erfolgreichem Löschen
        header('Location: dashboard.php?success=Eintrag gelöscht');
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Löschen des Eintrags: ' . $e->getMessage());
    }
} else {
    die('Ungültige Anfrage.');
}
?>
