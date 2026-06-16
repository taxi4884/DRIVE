<?php
require_once '../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['umsatzid'])) {
    die('Ungültiger Aufruf.');
}

$umsatzid = (int)$_POST['umsatzid'];
$fahrerId = (int)($_POST['fahrer_id'] ?? 0);
$betrag = isset($_POST['betrag']) ? (float)str_replace(',', '.', (string)$_POST['betrag']) : 0.0;
if ($betrag < 0) { $betrag = 0.0; }

$user = trim((string)($_SESSION['user_name'] ?? 'System'));

$stmt = $pdo->prepare('UPDATE Umsatz SET EingezahltBetrag = IFNULL(EingezahltBetrag,0) + :betrag, EingezahltAm = NOW(), EingezahltVon = :von WHERE UmsatzID = :id');
$stmt->execute([
    ':betrag' => $betrag,
    ':von' => $user,
    ':id' => $umsatzid,
]);

$redirect = 'fahrer_umsatz.php?fahrer_id=' . urlencode((string)$fahrerId);
if (isset($_POST['start_date']) && $_POST['start_date'] !== '') {
    $redirect .= '&start_date=' . urlencode((string)$_POST['start_date']);
}
if (isset($_POST['end_date']) && $_POST['end_date'] !== '') {
    $redirect .= '&end_date=' . urlencode((string)$_POST['end_date']);
}

header('Location: ' . $redirect);
exit;
