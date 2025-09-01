<?php
require_once '../includes/head.php';

$fahrer_id = $_POST['fahrer_id'];
$original_start = $_POST['original_start'];
$original_end = $_POST['original_end'];
$new_start = $_POST['startdatum'];
$new_end = $_POST['enddatum'];
$grund = $_POST['grund'];

$stmt = $pdo->prepare("UPDATE FahrerAbwesenheiten SET startdatum = ?, enddatum = ?, grund = ? 
    WHERE FahrerID = ? AND startdatum = ? AND enddatum = ? AND abwesenheitsart = 'Urlaub'");
$stmt->execute([$new_start, $new_end, $grund, $fahrer_id, $original_start, $original_end]);

echo "OK";
