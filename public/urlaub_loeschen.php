<?php
// urlaub_loeschen.php
require_once '../includes/bootstrap.php';

$fahrer_id = $_GET['id'];
$von = $_GET['von'];
$bis = $_GET['bis'];

$stmt = $pdo->prepare("DELETE FROM FahrerAbwesenheiten WHERE FahrerID = ? AND startdatum = ? AND enddatum = ? AND abwesenheitsart = 'Urlaub'");
$stmt->execute([$fahrer_id, $von, $bis]);

header("Location: fahrer_bearbeiten.php?id=" . urlencode($fahrer_id));
exit;
