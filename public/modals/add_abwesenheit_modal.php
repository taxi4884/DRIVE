<?php
//add_abwesenheit_modal.php

require_once '../includes/db.php'; // Datenbankverbindung

// Mitarbeiter abrufen
try {
    $stmtMitarbeiter = $pdo->query("SELECT mitarbeiter_id, CONCAT(vorname, ' ', nachname) AS name FROM mitarbeiter_zentrale WHERE status = 'Aktiv' ORDER BY nachname ASC");
    $mitarbeiter = $stmtMitarbeiter->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Fehler beim Abrufen der Mitarbeiter: ' . $e->getMessage());
}
?>

<div class="modal" id="abwesenheitModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('abwesenheitModal')">&times;</span>
        <h2>Krankmeldung erfassen</h2>
			<form action="../modals/process_abwesenheit.php" method="POST">
				<label for="mitarbeiter_id">Mitarbeiter:</label>
				<select id="mitarbeiter_id" name="mitarbeiter_id" required>
					<option value="" disabled selected>Wähle einen Mitarbeiter</option>
					<?php foreach ($mitarbeiter as $person): ?>
						<option value="<?= htmlspecialchars($person['mitarbeiter_id']) ?>">
							<?= htmlspecialchars($person['name'] ?? 'Unbekannter Mitarbeiter') ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="hidden" id="typ" name="typ" value="Krank">
				<label for="startdatum">Startdatum:</label>
				<input type="date" id="startdatum" name="startdatum" required>
				<label for="enddatum">Enddatum:</label>
				<input type="date" id="enddatum" name="enddatum" required>
				<label for="bemerkungen">Bemerkungen (optional):</label>
				<textarea id="bemerkungen" name="bemerkungen" rows="3"></textarea>
				<button type="submit" name="add_abwesenheit">Krankmeldung speichern</button>
			</form>
    </div>
</div>
