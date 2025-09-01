<?php
require_once '../includes/db.php'; // Datenbankverbindung

// Verarbeitung: Neues Fahrzeug hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $concession_number = trim($_POST['concession_number']);
    $license_plate = trim($_POST['license_plate']);
    $mileage = (int)$_POST['mileage'];
    $hu_date = $_POST['hu_date'];
    $eichungsdatum = $_POST['eichungsdatum'];

    // Validierung der Pflichtfelder
    if (
        empty($brand) || empty($model) || empty($concession_number) || 
        empty($license_plate) || empty($hu_date) || empty($eichungsdatum) || $mileage < 0
    ) {
        die('Alle Pflichtfelder müssen ausgefüllt werden!');
    }

    try {
        // Fahrzeug in die Datenbank einfügen
        $stmt = $pdo->prepare("
            INSERT INTO Fahrzeuge (
                Marke, Modell, Konzessionsnummer, Kennzeichen, 
                AnfangsKilometerstand, HU, Eichung
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $brand, $model, $concession_number, $license_plate, 
            $mileage, $hu_date, $eichungsdatum
        ]);

        // Zurück zur vorherigen Seite
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Hinzufügen des Fahrzeugs: ' . $e->getMessage());
    }
}
?>
