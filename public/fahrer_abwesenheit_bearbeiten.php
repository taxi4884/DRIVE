<?php
require_once '../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: abwesenheit_fahrer.php');
    exit;
}

$abwesenheitId = (int)($_POST['abwesenheit_id'] ?? 0);
$action = $_POST['action'] ?? 'update';

if ($abwesenheitId <= 0) {
    header('Location: abwesenheit_fahrer.php');
    exit;
}

if ($action === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM FahrerAbwesenheiten WHERE id = ?');
    $stmt->execute([$abwesenheitId]);

    header('Location: abwesenheit_fahrer.php?updated=1');
    exit;
}

$abwesenheitsart = trim((string)($_POST['abwesenheitsart'] ?? ''));
$grund = trim((string)($_POST['grund'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));
$startdatum = trim((string)($_POST['startdatum'] ?? ''));
$enddatum = trim((string)($_POST['enddatum'] ?? ''));
$kommentar = trim((string)($_POST['kommentar'] ?? ''));

if ($abwesenheitsart === '' || $grund === '' || $startdatum === '' || $enddatum === '') {
    header('Location: abwesenheit_fahrer.php');
    exit;
}

if ($abwesenheitsart !== 'Urlaub') {
    $status = null;
}

$stmt = $pdo->prepare('UPDATE FahrerAbwesenheiten SET abwesenheitsart = ?, grund = ?, status = ?, startdatum = ?, enddatum = ?, kommentar = ? WHERE id = ?');
$stmt->execute([
    $abwesenheitsart,
    $grund,
    $status,
    $startdatum,
    $enddatum,
    $kommentar !== '' ? $kommentar : null,
    $abwesenheitId,
]);

header('Location: abwesenheit_fahrer.php?updated=1');
exit;
