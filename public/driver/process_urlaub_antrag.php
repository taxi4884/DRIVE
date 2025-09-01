<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../includes/db.php'; // Passe den Pfad an, falls nötig.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sicherstellen, dass der Fahrer eingeloggt ist
    if (!isset($_SESSION['user_id'])) {
        die('Nicht autorisiert.');
    }
    $fahrer_id = $_SESSION['user_id'];

    // Daten aus dem Formular sammeln
    $startdatum = $_POST['startdatum'] ?? null;
    $enddatum = $_POST['enddatum'] ?? null;
    $kommentar = $_POST['kommentar'] ?? '';

    // Grundlegende Validierung
    if (!$startdatum || !$enddatum) {
        die('Bitte Start- und Enddatum angeben.');
    }

    // Neuen Urlaubsantrag einfügen
    try {
        $insertQuery = "
            INSERT INTO FahrerAbwesenheiten 
            (FahrerID, abwesenheitsart, grund, status, startdatum, enddatum, kommentar, erstellt_am, aktualisiert_am)
            VALUES (?, 'Urlaub', 'Urlaub', 'beantragt', ?, ?, ?, NOW(), NOW())
        ";
        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([$fahrer_id, $startdatum, $enddatum, $kommentar]);
        
        // Weiterleiten zurück zur persönlichen Daten-Seite mit Erfolgsmeldung
        header('Location: personal.php?success=1');
        exit;
    } catch (PDOException $e) {
        die('Datenbankfehler: ' . $e->getMessage());
    }
} else {
    die('Ungültige Anfrage.');
}
?>
