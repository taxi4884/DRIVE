<?php
require_once '../includes/db.php'; // Datenbankverbindung

// Verarbeitung: Neuen Fahrer hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $pschein = $_POST['pschein'];
    $license = $_POST['license'];
    $license_number = trim($_POST['license_number']);
    $street = trim($_POST['street']);
    $house_number = trim($_POST['house_number']);
    $zip = trim($_POST['zip']);
    $city = trim($_POST['city']);
    $fahrernummer = trim($_POST['fahrernummer']);
    $code = trim($_POST['code']);

    // Validierung der Pflichtfelder
    if (
        empty($firstname) || empty($lastname) || empty($phone) || empty($email) ||
        empty($pschein) || empty($license) || empty($license_number) || 
        empty($street) || empty($house_number) || empty($zip) || empty($city) || 
        empty($fahrernummer) || empty($code)
    ) {
        die('Alle Pflichtfelder müssen ausgefüllt werden!');
    }

    try {
        // Fahrer in die Datenbank einfügen
        $stmt = $pdo->prepare("
            INSERT INTO Fahrer (
                Vorname, Nachname, Telefonnummer, Email, PScheinGueltigkeit, 
                FuehrerscheinGueltigkeit, Fuehrerscheinnummer, Strasse, 
                Hausnummer, PLZ, Ort, Fahrernummer, Code
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $firstname, $lastname, $phone, $email, $pschein, $license, 
            $license_number, $street, $house_number, $zip, $city, 
            $fahrernummer, $code // Passwort unverschlüsselt speichern
        ]);

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Hinzufügen des Fahrers: ' . $e->getMessage());
    }
}
?>