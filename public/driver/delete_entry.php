<?php
require_once '../../includes/bootstrap.php'; // Lädt Authentifizierung und Datenbankverbindung
require_once '../../includes/driver_helpers.php';
require_once '../../includes/umsatz_repository.php';

try {
    $fahrer_id = requireDriverId();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$umsatzRepository = new UmsatzRepository($pdo);

// Überprüfung, ob das Datum übergeben wurde
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['datum'])) {
    $datum = $_GET['datum'];

    // Validierung des Datums
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) { // Prüft auf das Format YYYY-MM-DD
        die('Ungültiges Datumsformat.');
    }

    $eintrag = $umsatzRepository->getByDriverAndDate($fahrer_id, $datum);

    if (!$eintrag) {
        die('Eintrag nicht gefunden oder keine Berechtigung, diesen Eintrag zu löschen.');
    }

    // Eintrag löschen
    try {
        $umsatzRepository->delete($fahrer_id, $datum);

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
