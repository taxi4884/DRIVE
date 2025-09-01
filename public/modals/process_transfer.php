<?php
require_once '../includes/db.php'; // Datenbankverbindung

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transfer'])) {
    $vehicle_id = (int)$_POST['vehicle_id'];
    $driver_id = (int)$_POST['driver_id'];
    $transfer_date = $_POST['transfer_date'];
    $mileage = (int)$_POST['mileage'];
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($vehicle_id) || empty($driver_id) || empty($transfer_date) || $mileage < 0) {
        die('Alle Pflichtfelder müssen ausgefüllt werden!');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO FahrzeugUebergabe (FahrzeugID, FahrerID, UebergabeDatum, Kilometerstand, Bemerkungen)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$vehicle_id, $driver_id, $transfer_date, $mileage, $remarks]);

        header("Location: fahrzeuge.php?success=Übergabeprotokoll gespeichert");
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Speichern des Übergabeprotokolls: ' . $e->getMessage());
    }
}
?>
