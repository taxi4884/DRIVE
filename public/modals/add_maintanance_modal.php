<?php
require_once '../includes/db.php'; // Datenbankverbindung

// Verarbeitung Wartungserfassung
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

        header("Location: fahrzeuge.php");
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Speichern der Wartung: ' . $e->getMessage());
    }
}

// Fahrzeuge abrufen
try {
    $stmtVehicles = $pdo->query("
        SELECT FahrzeugID, Konzessionsnummer, Marke, Modell
        FROM Fahrzeuge
        ORDER BY Konzessionsnummer ASC
    ");
    $fahrzeuge = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

    // Überprüfen, ob Daten abgerufen wurden
    if (!$fahrzeuge) {
        die('Es wurden keine Fahrzeuge gefunden.');
    }
} catch (PDOException $e) {
    die('Fehler beim Abrufen der Fahrzeuge: ' . $e->getMessage());
}
?>

<div class="modal" id="maintenanceModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('maintenanceModal')">&times;</span>
        <h2>Wartungstermin hinzufügen</h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <label for="vehicle_id">Fahrzeug:</label>
            <select id="vehicle_id" name="vehicle_id" required>
                <option value="" disabled selected>Wähle ein Fahrzeug</option>
                <?php foreach ($fahrzeuge as $fahrzeug): ?>
                    <option value="<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>">
                        <?= htmlspecialchars($fahrzeug['Konzessionsnummer'] ?? 'Keine Konzessionsnummer') ?> - 
                        <?= htmlspecialchars($fahrzeug['Marke'] ?? 'Keine Marke') ?> 
                        <?= htmlspecialchars($fahrzeug['Modell'] ?? 'Kein Modell') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br>
            <label for="maintenance_date">Datum der Wartung:</label>
            <input type="date" id="maintenance_date" name="maintenance_date" required>
            <br>
            <label for="description">Beschreibung:</label>
            <textarea id="description" name="description" placeholder="Beschreibung der Wartung" required></textarea>
            <br>
            <label for="costs">Kosten (€):</label>
            <input type="number" id="costs" name="costs" step="0.01" placeholder="Kosten der Wartung" required>
            <br>
            <label for="workshop">Werkstatt:</label>
            <input type="text" id="workshop" name="workshop" placeholder="Name der Werkstatt" required>
            <br>
            <label for="mileage">Kilometerstand:</label>
            <input type="number" id="mileage" name="mileage" placeholder="Kilometerstand bei Wartung" required>
            <br>
            <label for="remarks">Bemerkung:</label>
            <textarea id="remarks" name="remarks" placeholder="Zusätzliche Hinweise"></textarea>
            <br>
            <button type="submit" name="add_maintenance">Wartung speichern</button>
        </form>
    </div>
</div>

