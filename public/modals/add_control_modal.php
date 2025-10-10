<?php
require_once '../includes/db.php'; // Datenbankverbindung

// Fahrzeuge abrufen
try {
    $stmtVehicles = $pdo->query("
        SELECT FahrzeugID, Konzessionsnummer, Marke, Modell
        FROM Fahrzeuge
        ORDER BY Konzessionsnummer ASC
    ");
    $fahrzeuge = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Fehler beim Abrufen der Fahrzeuge: ' . $e->getMessage());
}

// Fahrer abrufen
try {
    $stmtDrivers = $pdo->query("
        SELECT FahrerID, CONCAT(Vorname, ' ', Nachname) AS Name
        FROM Fahrer
        WHERE Status IN ('aktiv', 'Aktiv')
        ORDER BY Nachname ASC
    ");
    $fahrer = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Fehler beim Abrufen der Fahrer: ' . $e->getMessage());
}
?>

<div class="modal" id="controlModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('controlModal')">&times;</span>
        <h2>Fahrzeugkontrolle</h2>
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
                    <option value="<?= htmlspecialchars($driver['FahrerID']) ?>">
                        <?= htmlspecialchars($driver['Name'] ?? 'Unbekannter Fahrer') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br>

            <div class="criteria-container">
				<fieldset>
					<legend>Sauberkeit außen:</legend>
					<?php for ($i = 1; $i <= 6; $i++): ?>
						<label>
							<input type="radio" name="sauberkeit_aussen" value="<?= $i ?>" required>
							<?= $i ?>
						</label>
					<?php endfor; ?>
				</fieldset>
				<fieldset>
					<legend>Sauberkeit innen:</legend>
					<?php for ($i = 1; $i <= 6; $i++): ?>
						<label>
							<input type="radio" name="sauberkeit_innen" value="<?= $i ?>" required>
							<?= $i ?>
						</label>
					<?php endfor; ?>
				</fieldset>
				<label for="reifendruck">Reifendruck (Bar):</label>
				<input type="number" id="reifendruck" name="reifendruck" step="0.1" min="0" required>
				<br>
				<fieldset>
					<legend>Reifenzustand:</legend>
					<?php for ($i = 1; $i <= 6; $i++): ?>
						<label>
							<input type="radio" name="reifenzustand" value="<?= $i ?>" required>
							<?= $i ?>
						</label>
					<?php endfor; ?>
				</fieldset>
				<fieldset>
					<legend>Bremsenzustand:</legend>
					<?php for ($i = 1; $i <= 6; $i++): ?>
						<label>
							<input type="radio" name="bremsenzustand" value="<?= $i ?>" required>
							<?= $i ?>
						</label>
					<?php endfor; ?>
				</fieldset>
				<label for="kilometerstand">Kilometerstand:</label>
				<input type="number" id="kilometerstand" name="kilometerstand" min="0" required>
				<br>
				<label for="bemerkung">Bemerkungen (optional):</label>
				<textarea id="bemerkung" name="bemerkung" rows="3"></textarea>
				<br>
			</div>
            <button type="submit" name="add_control">Kontrolle speichern</button>
        </form>
    </div>
</div>
