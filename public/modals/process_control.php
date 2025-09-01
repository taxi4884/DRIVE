<?php
require_once '../includes/db.php'; // Datenbankverbindung

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_control'])) {
    $vehicle_id = (int)$_POST['vehicle_id'];
    $driver_id = (int)$_POST['driver_id'];
    $sauberkeit_aussen = (int)$_POST['sauberkeit_aussen'];
    $sauberkeit_innen = (int)$_POST['sauberkeit_innen'];
    $reifendruck = (float)$_POST['reifendruck']; // Numerischer Wert für Reifendruck
    $reifenzustand = (int)$_POST['reifenzustand'];
    $bremsenzustand = (int)$_POST['bremsenzustand'];
    $kilometerstand = (int)$_POST['kilometerstand'];
    $bemerkung = trim($_POST['bemerkung'] ?? '');

    if (
        empty($vehicle_id) || empty($driver_id) || $sauberkeit_aussen < 1 || 
        $sauberkeit_innen < 1 || $reifendruck <= 0 || $reifenzustand < 1 || 
        $bremsenzustand < 1 || $kilometerstand <= 0
    ) {
        die('Alle Pflichtfelder müssen ausgefüllt werden!');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO Fahrzeugkontrollen 
            (FahrzeugID, FahrerID, KontrollDatum, SauberkeitAussen, SauberkeitInnen, Reifendruck, Reifenzustand, Bremsenzustand, Kilometerstand, Bemerkung)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$vehicle_id, $driver_id, $sauberkeit_aussen, $sauberkeit_innen, $reifendruck, $reifenzustand, $bremsenzustand, $kilometerstand, $bemerkung]);

        header('Location: fahrzeuge.php?success=Kontrolle gespeichert');
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Speichern der Kontrolle: ' . $e->getMessage());
    }
}
?>
