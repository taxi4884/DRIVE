<?php
require_once '../includes/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE FahrerAbwesenheiten SET status = 'genehmigt' WHERE id = :id");
    $stmt->execute(['id' => $id]);
}

header('Location: abwesenheit_fahrer.php');
exit;
?>
