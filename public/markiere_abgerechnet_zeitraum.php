<?php
require_once '../includes/head.php';

$start = $_POST['start_date'] ?? null;
$end = $_POST['end_date'] ?? null;
$fahrer_id = $_POST['fahrer_id'] ?? null;

if (!$start || !$end || !$fahrer_id) {
    die("Fehlende Angaben.");
}

$stmt = $pdo->prepare("
    UPDATE Umsatz
    SET Abgerechnet = 1
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
");
$stmt->execute([$fahrer_id, $start, $end]);

header("Location: fahrer_umsatz.php?fahrer_id=" . urlencode($fahrer_id));
exit();
