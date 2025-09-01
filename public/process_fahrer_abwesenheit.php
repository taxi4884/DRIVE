<?php
require_once '../../includes/db.php'; // Datenbankverbindung

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fahrer_id = $_POST['fahrer_id'] ?? null;
    $abwesenheitsart = $_POST['abwesenheitsart'] ?? null;
    $grund = $_POST['grund'] ?? null;
    $startdatum = $_POST['startdatum'] ?? null;
    $enddatum = $_POST['enddatum'] ?? null;
    $kommentar = $_POST['kommentar'] ?? null;

    // Grundlegende Validierung der Eingaben
    if (!$fahrer_id || !$abwesenheitsart || !$grund || !$startdatum || !$enddatum) {
        die('Bitte alle erforderlichen Felder ausfüllen.');
    }

    $status = null;
    if ($abwesenheitsart === 'Urlaub') {
        $status = 'beantragt';
    }

    // Beantragt_von setzen: Annahme - aktueller Benutzer ist der Antragsteller
    $beantragt_von_benutzer_id = $_SESSION['user_id'] ?? null;
    $beantragt_von_fahrer_id = null;
    // Je nach Logik: Falls Fahrer selbst einreicht, setze beantragt_von_fahrer_id

    $stmt = $pdo->prepare("
      INSERT INTO FahrerAbwesenheiten 
      (FahrerID, abwesenheitsart, grund, status, startdatum, enddatum, 
       beantragt_von_fahrer_id, beantragt_von_benutzer_id, kommentar) 
      VALUES 
      (:fahrer_id, :abwesenheitsart, :grund, :status, :startdatum, :enddatum, 
       :beantragt_von_fahrer_id, :beantragt_von_benutzer_id, :kommentar)
    ");

    $stmt->execute([
        'fahrer_id' => $fahrer_id,
        'abwesenheitsart' => $abwesenheitsart,
        'grund' => $grund,
        'status' => $status,
        'startdatum' => $startdatum,
        'enddatum' => $enddatum,
        'beantragt_von_fahrer_id' => $beantragt_von_fahrer_id,
        'beantragt_von_benutzer_id' => $beantragt_von_benutzer_id,
        'kommentar' => $kommentar
    ]);

    header('Location: fahrer_abwesenheiten.php?success=1');
    exit;
}
?>
