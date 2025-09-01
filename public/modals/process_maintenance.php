<?php
require_once '../includes/db.php'; // Datenbankverbindung

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
    $vehicle_id = (int)$_POST['vehicle_id'];
    $maintenance_date = $_POST['maintenance_date'];
    $description = trim($_POST['description']);
    $costs = (float)$_POST['costs'];
    $workshop = trim($_POST['workshop']);
    $mileage = (int)$_POST['mileage'];
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($vehicle_id) || empty($maintenance_date) || empty($description) || $costs <= 0 || empty($workshop) || $mileage < 0) {
        die('Alle Pflichtfelder müssen ausgefüllt werden!');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO Wartung (FahrzeugID, Wartungsdatum, Beschreibung, Kosten, Werkstatt, Kilometerstand, Bemerkungen)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$vehicle_id, $maintenance_date, $description, $costs, $workshop, $mileage, $remarks]);

        header("Location: fahrzeuge.php?success=Wartung gespeichert");
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Speichern der Wartung: ' . $e->getMessage());
    }
}
