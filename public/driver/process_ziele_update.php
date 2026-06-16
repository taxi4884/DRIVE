<?php
require_once '../../includes/head.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$fahrer_id = (int) $_SESSION['user_id'];

// Eingaben holen und sanft normalisieren
$schichtziel = $_POST['standard_schichtziel'] ?? null;
$monatsziel  = $_POST['standard_monatsziel'] ?? null;

$schichtziel = $schichtziel !== '' ? (float) str_replace(',', '.', $schichtziel) : null;
$monatsziel  = $monatsziel  !== '' ? (float) str_replace(',', '.', $monatsziel) : null;

try {
    $stmt = $pdo->prepare("
        UPDATE Fahrer
        SET standard_schichtziel = :schichtziel,
            standard_monatsziel  = :monatsziel
        WHERE FahrerID = :fahrer_id
    ");
    $stmt->execute([
        ':schichtziel' => $schichtziel,
        ':monatsziel'  => $monatsziel,
        ':fahrer_id'   => $fahrer_id,
    ]);

    // Du könntest später noch ein Feedback-Flag anhängen, z. B. ?goals=1
    header('Location: personal.php?goals=1');
    exit;
} catch (PDOException $e) {
    // Fürs Debugging kannst du loggen, für den Fahrer einfach zurück auf die Seite
    // und ggf. ein Fehlerbanner anzeigen
    header('Location: personal.php?goals_error=1');
    exit;
}
