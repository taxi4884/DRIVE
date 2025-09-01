<?php
// public/speichere_abrechungsinfo.php
require_once '../includes/head.php';

$datum = $_POST['datum'] ?? null;
$uhrzeit = trim($_POST['uhrzeit'] ?? '');

if (!$datum || $uhrzeit === '') {
    die("Datum und Uhrzeit sind erforderlich.");
}

$stmt = $pdo->prepare("
    INSERT INTO Abrechnungsplanung (Datum, Uhrzeit)
    VALUES (?, ?)
");
$stmt->execute([$datum, $uhrzeit]);

header("Location: fahrer_umsatz.php");
exit();
