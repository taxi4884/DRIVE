<?php
require_once '../includes/db.php'; // Datenbankverbindung

// Fahrzeuge abrufen
$stmtVehicles = $pdo->query("
    SELECT FahrzeugID, Konzessionsnummer, Marke, Modell
    FROM Fahrzeuge
    ORDER BY Konzessionsnummer ASC
");
$fahrzeuge = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

// Fahrer abrufen
try {
    $stmtDrivers = $pdo->query("
        SELECT FahrerID, CONCAT(Vorname, ' ', Nachname) AS Name
        FROM Fahrer
        WHERE Status IN ('aktiv', 'Aktiv')
        ORDER BY Nachname ASC
    ");
    $fahrer = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$fahrer) {
        die('Keine Fahrer gefunden.');
    }
} catch (PDOException $e) {
    die('Fehler beim Abrufen der Fahrer: ' . $e->getMessage());
}
?>

<div class="modal" id="transferModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('transferModal')">&times;</span>
        <h2>Fahrzeugübergabe</h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <label for="vehicle_id">Fahrzeug:</label>
            <select id="vehicle_id" name="vehicle_id" required>
                <option value="" disabled selected>Wähle ein Fahrzeug</option>
                <?php foreach ($fahrzeuge as $fahrzeug): ?>
                    <option value="<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>">
                        <?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?> -
                        <?= htmlspecialchars($fahrzeug['Marke'] ?? 'Keine Marke') ?> 
                        <?= htmlspecialchars($fahrzeug['Modell'] ?? 'Kein Modell') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br>

            <label for="driver_id">Fahrer:</label>
            <select id="driver_id" name="driver_id" required>
                <option value="" disabled selected>Wähle einen Fahrer</option>
                <?php foreach ($fahrer as $driver): ?>
                    <?php if (!isset($driver['FahrerID'], $driver['Name'])) continue; ?>
                    <option value="<?= htmlspecialchars($driver['FahrerID']) ?>">
                        <?= htmlspecialchars($driver['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br>

            <label for="transfer_date">Datum der Übergabe:</label>
            <input type="date" id="transfer_date" name="transfer_date" required>
            <br>

            <label for="mileage">Kilometerstand:</label>
            <input type="number" id="mileage" name="mileage" min="0" required>
            <br>

            <label for="remarks">Bemerkungen (optional):</label>
            <textarea id="remarks" name="remarks" rows="3"></textarea>
            <br>

            <button type="submit" name="add_transfer">Übergabeprotokoll speichern</button>
        </form>
    </div>
</div>
