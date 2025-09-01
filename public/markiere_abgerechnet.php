<?php
require_once '../includes/head.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['umsatzid'])) {
    $umsatzid = (int) $_POST['umsatzid'];

    $stmt = $pdo->prepare("UPDATE Umsatz SET Abgerechnet = 1 WHERE UmsatzID = ?");
    $stmt->execute([$umsatzid]);

    // Weiterleitung zurück
    $redirect = 'fahrer_umsatz.php?fahrer_id=' . urlencode($_POST['fahrer_id']);
    if (isset($_GET['start_date'])) {
        $redirect .= '&start_date=' . urlencode($_GET['start_date']);
    }
    if (isset($_GET['end_date'])) {
        $redirect .= '&end_date=' . urlencode($_GET['end_date']);
    }

    header("Location: $redirect");
    exit;
} else {
    echo "Ungültiger Aufruf.";
}
