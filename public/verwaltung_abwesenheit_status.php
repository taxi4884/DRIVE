<?php
require_once '../includes/head.php';

if (!isLoggedIn()) {
    die("Nicht eingeloggt.");
}

if (!isset($_POST['action'], $_POST['abwesenheit_ids']) || !in_array($_POST['action'], ['approve', 'reject'])) {
    die("Ungültige Anfrage.");
}

$ids = $_POST['abwesenheit_ids'];
$aktion = $_POST['action'];
$benutzerId = $_SESSION['user_id'];

$statusZeit = date('Y-m-d H:i:s');

try {
    foreach ($ids as $id) {
        if ($aktion === 'approve') {
            $stmt = $pdo->prepare("UPDATE verwaltung_abwesenheit SET genehmigt_am = :zeit, genehmigt_von = :benutzer WHERE id = :id");
        } else {
            $stmt = $pdo->prepare("UPDATE verwaltung_abwesenheit SET abgelehnt_am = :zeit, abgelehnt_von = :benutzer WHERE id = :id");
        }

        $stmt->execute([
            ':zeit' => $statusZeit,
            ':benutzer' => $benutzerId,
            ':id' => $id
        ]);
    }

    header("Location: verwaltung_abwesenheit.php?success=1");
    exit;
} catch (PDOException $e) {
    die("Fehler bei der Statusänderung: " . $e->getMessage());
}
