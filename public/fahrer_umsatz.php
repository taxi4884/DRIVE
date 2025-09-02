<?php
// public/fahrer_umsatz.php
require_once '../includes/bootstrap.php';

// Fahrer aus der Datenbank laden (nur aktive Fahrer, sortiert nach Personalnummer & Nachname)
$stmtAlleFahrer = $pdo->query("
    SELECT FahrerID, Personalnummer, CONCAT(Vorname, ' ', Nachname) AS Name 
    FROM Fahrer 
    WHERE Status = 'Aktiv'
    ORDER BY Personalnummer IS NULL, Personalnummer ASC, Nachname ASC
");
$alleFahrer = $stmtAlleFahrer->fetchAll(PDO::FETCH_ASSOC);

// Standardzeitraum definieren
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Werte aus GET übernehmen
if (isset($_GET['start_date'])) {
    $start_date = date('Y-m-d', strtotime($_GET['start_date']));
}
if (isset($_GET['end_date'])) {
    $end_date = date('Y-m-d', strtotime($_GET['end_date']));
}
$fahrer_id = $_GET['fahrer_id'] ?? ($alleFahrer[0]['FahrerID'] ?? null);

if (!$fahrer_id) {
    die('Keine Fahrer vorhanden.');
}

// Fahrerinformationen abrufen
$stmtFahrer = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS Name FROM Fahrer WHERE FahrerID = ?");
$stmtFahrer->execute([$fahrer_id]);
$fahrer = $stmtFahrer->fetch(PDO::FETCH_ASSOC);

if (!$fahrer) {
    die('Fahrer nicht gefunden!');
}

// Gesamtumsatz abrufen
$stmtGesamt = $pdo->prepare("
    SELECT SUM(TaxameterUmsatz + OhneTaxameter) AS GesamtUmsatz
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
");
$stmtGesamt->execute([$fahrer_id, $start_date, $end_date]);
$gesamtUmsatz = $stmtGesamt->fetchColumn() ?? 0;

// Detaillierte Umsätze abrufen – hier wird auch die UmsatzID benötigt!
$stmtUmsatz = $pdo->prepare("
    SELECT 
		UmsatzID,
		Datum, 
		TaxameterUmsatz, 
		OhneTaxameter, 
		Kartenzahlung, 
		Rechnungsfahrten, 
		Krankenfahrten, 
		Gutscheine, 
		Alita, 
		TankenWaschen, 
		SonstigeAusgaben,
		Notiz,
		Abgerechnet
	FROM Umsatz
	WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
	ORDER BY Datum ASC
");
$stmtUmsatz->execute([$fahrer_id, $start_date, $end_date]);
$umsatzDaten = $stmtUmsatz->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrer Umsatz | DRIVE</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --highlight-color: #ffcc00;
            --highlight-text: #000;
            --light-grey: #f5f5f5;
            --border-color: #ccc;
            --text-muted: #555;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        h1, h2, h3 {
            margin-top: 0;
        }

        .section-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .section-container section {
            flex: 1;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            .section-container {
                flex-direction: column;
            }
        }

        form.flex-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            background-color: white;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        form.flex-container label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        form.flex-container select,
        form.flex-container input[type="date"] {
            padding: 6px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1rem;
        }

        button {
            padding: 8px 12px;
            font-size: 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button i {
            margin-right: 6px;
        }

        .action-btn {
            background-color: var(--highlight-color);
            color: var(--highlight-text);
            padding: 6px 10px;
            border-radius: 5px;
            margin: 2px;
            display: inline-flex;
            align-items: center;
        }

        .action-btn:hover {
            background-color: #f7c600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        table th {
            background-color: var(--highlight-color);
            color: var(--highlight-text);
        }

        .modal, .modal-overlay {
            display: none;
        }

        .modal.active, .modal-overlay.active {
            display: block;
        }

        .modal {
            position: fixed;
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 500px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
        }

        .modal input,
        .modal textarea {
            width: 100%;
            margin-bottom: 12px;
            padding: 8px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
        }

        .modal button {
            margin-top: 10px;
            font-size: 1rem;
        }

        .text-muted {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background-color: var(--light-grey);
            color: var(--text-muted);
        }

        .btn-secondary:hover {
            background-color: #e1e1e1;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <main>
        <h1>Umsätze</h1>

        <!-- Fahrerwechsel und Zeitraum -->
        <form method="GET" class="flex-container">
			<div>
				<label for="fahrer_id"><i class="fas fa-user"></i> Fahrer:</label>
				<select id="fahrer_id" name="fahrer_id" onchange="this.form.submit()">
					<?php foreach ($alleFahrer as $fahrerOption): ?>
						<option value="<?= htmlspecialchars($fahrerOption['FahrerID']) ?>"
							<?= $fahrerOption['FahrerID'] == $fahrer_id ? 'selected' : '' ?>>
							<?= !empty($fahrerOption['Personalnummer']) 
								? htmlspecialchars($fahrerOption['Personalnummer']) . ' - ' . htmlspecialchars($fahrerOption['Name']) 
								: htmlspecialchars($fahrerOption['Name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

            <div>
                <label for="start_date"><i class="fas fa-calendar-alt"></i> Zeitraum:</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                <button type="submit"><i class="fas fa-search"></i> Anzeigen</button>
            </div>
        </form>
		
		<div class="section-container">
			<!-- Gesamtübersicht -->
			<section>
				<h2>Gesamtübersicht</h2>
				<p><strong>Gesamtumsatz:</strong> <?= number_format($gesamtUmsatz, 2, ',', '.') ?> €</p>
			</section>

			<!-- PDF-Export mit Zeitraum -->
			<section>
				<h2>PDF-Export</h2>
				<button id="showModalButton"><i class="fas fa-file-pdf"></i> Export als PDF</button>

				<div class="modal-overlay" id="modalOverlay"></div>
				<div class="modal" id="exportModal">
					<form action="export_fahrer_umsatz.php" method="GET" target="_blank">
						<label for="export_start_date"><i class="fas fa-calendar-alt"></i> Startdatum:</label>
						<input type="date" id="export_start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
						<label for="export_end_date"><i class="fas fa-calendar-alt"></i> Enddatum:</label>
						<input type="date" id="export_end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
						<input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer_id) ?>">
						<button type="submit"><i class="fas fa-check"></i> PDF erstellen</button>
						<button type="button" id="closeModalButton"><i class="fas fa-times"></i> Abbrechen</button>
					</form>
				</div>
			</section>

			<!-- Neuer Umsatz erfassen -->
			<section>
				<h2>Neuen Umsatz erfassen</h2>
				<button id="showAddModalButton"><i class="fas fa-plus"></i> Neuer Umsatz</button>
			</section>
			
			<!-- Zeitraum als abgerechnet markieren -->
			<section>
				<h2>Mehrfach-Abrechnung</h2>
				<button id="showAbrechnenModalButton"><i class="fas fa-check-double"></i> Zeitraum abrechnen</button>

				<div class="modal-overlay" id="abrechnenModalOverlay"></div>
				<div class="modal" id="abrechnenModal">
					<form action="markiere_abgerechnet_zeitraum.php" method="POST">
						<label for="abrechnen_start_date"><i class="fas fa-calendar-alt"></i> Startdatum:</label>
						<input type="date" id="abrechnen_start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>

						<label for="abrechnen_end_date"><i class="fas fa-calendar-alt"></i> Enddatum:</label>
						<input type="date" id="abrechnen_end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>

						<input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer_id) ?>">

						<button type="submit"><i class="fas fa-check"></i> Abrechnen</button>
						<button type="button" id="closeAbrechnenModalButton"><i class="fas fa-times"></i> Abbrechen</button>
					</form>
				</div>
			</section>
			
			<!-- Modal zur Abrechnungsankündigung -->
			<section>
				<h2>Abrechnungsinfo</h2>
				<button id="showPlanungModalButton"><i class="fas fa-calendar-check"></i> Fahrerinfo eingeben</button>

				<div class="modal-overlay" id="planungModalOverlay"></div>
				<div class="modal" id="planungModal">
					<form action="speichere_abrechnungsinfo.php" method="POST">
						<label for="datum"><i class="fas fa-calendar-day"></i> Datum:</label>
						<input type="date" name="datum" id="datum" required>

						<label for="uhrzeit"><i class="fas fa-clock"></i> Uhrzeit:</label>
						<input type="text" name="uhrzeit" id="uhrzeit" placeholder="z. B. 13:30 Uhr oder Nachmittag" required>

						<button type="submit"><i class="fas fa-check"></i> Speichern</button>
						<button type="button" id="closePlanungModalButton"><i class="fas fa-times"></i> Abbrechen</button>
					</form>
				</div>
			</section>
		</div>
		
        <!-- Detaillierte Umsätze -->
        <section>
            <h2>Detaillierte Umsätze</h2>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar-alt"></i> Datum</th>
                        <th><i class="fas fa-euro-sign"></i> Taxameter (€)</th>
                        <th><i class="fas fa-euro-sign"></i> Ohne Taxameter (€)</th>
                        <th><i class="fas fa-credit-card"></i> Kartenzahlungen (€)</th>
                        <th><i class="fas fa-file-invoice"></i> Rechnungsfahrten (€)</th>
                        <th><i class="fas fa-ambulance"></i> Krankenfahrten (€)</th>
                        <th><i class="fas fa-ticket-alt"></i> Gutscheine (€)</th>
                        <th><i class="fas fa-bus"></i> Alita (€)</th>
                        <th><i class="fas fa-gas-pump"></i> Tanken/Waschen (€)</th>
                        <th><i class="fas fa-money-bill-wave"></i> Sonstige Ausgaben (€)</th>
						<th><i class="fas fa-solid fa-note-sticky"></i> Notitz</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($umsatzDaten)): ?>
                        <?php foreach ($umsatzDaten as $umsatz): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d.m.Y', strtotime($umsatz['Datum']))) ?></td>
                                <td><?= number_format($umsatz['TaxameterUmsatz'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['OhneTaxameter'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['Kartenzahlung'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['Rechnungsfahrten'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['Krankenfahrten'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['Gutscheine'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['Alita'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['TankenWaschen'], 2, ',', '.') ?></td>
                                <td><?= number_format($umsatz['SonstigeAusgaben'], 2, ',', '.') ?></td>
								<td>
									<?php if (!empty($umsatz['Notiz'])): ?>
										<div style="font-size: 0.8em; color: #555; margin-top: 4px;">
											<?= nl2br(htmlspecialchars($umsatz['Notiz'])) ?>
										</div>
									<?php endif; ?>
								</td>
                                <td>
									<button class="action-btn editButton"
										data-umsatzid="<?= $umsatz['UmsatzID'] ?>"
										data-datum="<?= htmlspecialchars($umsatz['Datum']) ?>"
										data-taxameter="<?= htmlspecialchars($umsatz['TaxameterUmsatz']) ?>"
										data-ohnetaxameter="<?= htmlspecialchars($umsatz['OhneTaxameter']) ?>"
										data-kartenzahlung="<?= htmlspecialchars($umsatz['Kartenzahlung']) ?>"
										data-rechnungsfahrten="<?= htmlspecialchars($umsatz['Rechnungsfahrten']) ?>"
										data-krankenfahrten="<?= htmlspecialchars($umsatz['Krankenfahrten']) ?>"
										data-gutscheine="<?= htmlspecialchars($umsatz['Gutscheine']) ?>"
										data-alita="<?= htmlspecialchars($umsatz['Alita']) ?>"
										data-tankwaschen="<?= htmlspecialchars($umsatz['TankenWaschen']) ?>"
										data-sonstige="<?= htmlspecialchars($umsatz['SonstigeAusgaben']) ?>"
										onclick="openEditModal(this)">
										<i class="fas fa-edit"></i> Bearbeiten
									</button>

									<button class="action-btn deleteButton"
										data-umsatzid="<?= $umsatz['UmsatzID'] ?>"
										data-fahrerid="<?= htmlspecialchars($fahrer_id) ?>"
										onclick="confirmDelete(this)">
										<i class="fas fa-trash-alt"></i> Löschen
									</button>

									<?php if ($umsatz['Abgerechnet'] == 0): ?>
										<form method="POST" action="markiere_abgerechnet.php" style="display:inline;">
											<input type="hidden" name="umsatzid" value="<?= $umsatz['UmsatzID'] ?>">
											<input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer_id) ?>">
											<button type="submit" class="action-btn" onclick="return confirm('Diesen Umsatz als abgerechnet markieren?');">
												<i class="fas fa-check-circle"></i> Abrechnen
											</button>
										</form>
									<?php else: ?>
										<span style="color: green; font-size: 0.9em;"><i class="fas fa-check-circle"></i> Abgerechnet</span>
									<?php endif; ?>
									<button class="action-btn" onclick="openVerlaufModal(<?= $umsatz['UmsatzID'] ?>)">
										<i class="fas fa-clock"></i> Verlauf
									</button>
								</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11">Keine Umsätze für den gewählten Zeitraum.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal für PDF-Export -->
    <script>
        const showModalButton = document.getElementById('showModalButton');
        const closeModalButton = document.getElementById('closeModalButton');
        const exportModal = document.getElementById('exportModal');
        const overlay = document.getElementById('modalOverlay');

        showModalButton.addEventListener('click', () => {
            exportModal.classList.add('active');
            overlay.classList.add('active');
        });

        closeModalButton.addEventListener('click', () => {
            exportModal.classList.remove('active');
            overlay.classList.remove('active');
        });

        overlay.addEventListener('click', () => {
            exportModal.classList.remove('active');
            overlay.classList.remove('active');
            closeEditModal();
            closeAddModal();
        });
		
		const showAbrechnenModalButton = document.getElementById('showAbrechnenModalButton');
		const closeAbrechnenModalButton = document.getElementById('closeAbrechnenModalButton');
		const abrechnenModal = document.getElementById('abrechnenModal');
		const abrechnenOverlay = document.getElementById('abrechnenModalOverlay');

		showAbrechnenModalButton.addEventListener('click', () => {
			abrechnenModal.classList.add('active');
			abrechnenOverlay.classList.add('active');
		});

		closeAbrechnenModalButton.addEventListener('click', () => {
			abrechnenModal.classList.remove('active');
			abrechnenOverlay.classList.remove('active');
		});

		abrechnenOverlay.addEventListener('click', () => {
			abrechnenModal.classList.remove('active');
			abrechnenOverlay.classList.remove('active');
		});
		const showPlanungModalButton = document.getElementById('showPlanungModalButton');
		const closePlanungModalButton = document.getElementById('closePlanungModalButton');
		const planungModal = document.getElementById('planungModal');
		const planungOverlay = document.getElementById('planungModalOverlay');

		showPlanungModalButton.addEventListener('click', () => {
			planungModal.classList.add('active');
			planungOverlay.classList.add('active');
		});

		closePlanungModalButton.addEventListener('click', () => {
			planungModal.classList.remove('active');
			planungOverlay.classList.remove('active');
		});

		planungOverlay.addEventListener('click', () => {
			planungModal.classList.remove('active');
			planungOverlay.classList.remove('active');
		});
    </script>

	<!-- Modal für Umsatz bearbeiten -->
	<div class="modal" id="editModal">
		<form action="update_umsatz.php" method="POST">
			<h2>Umsatz bearbeiten</h2>
			<input type="hidden" name="umsatzid" id="edit_umsatzid">
			<input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer_id) ?>">

			<label for="edit_datum"><i class="fas fa-calendar-alt"></i> Datum:</label>
			<input type="date" id="edit_datum" name="datum" required>

			<label for="edit_taxameter"><i class="fas fa-euro-sign"></i> Taxameter:</label>
			<input type="number" step="0.01" id="edit_taxameter" name="taxameter" required>

			<label for="edit_ohnetaxameter"><i class="fas fa-euro-sign"></i> Ohne Taxameter:</label>
			<input type="number" step="0.01" id="edit_ohnetaxameter" name="ohnetaxameter" required>

			<label for="edit_kartenzahlung"><i class="fas fa-credit-card"></i> Kartenzahlung:</label>
			<input type="number" step="0.01" id="edit_kartenzahlung" name="kartenzahlung" required>

			<label for="edit_rechnungsfahrten"><i class="fas fa-file-invoice"></i> Rechnungsfahrten:</label>
			<input type="number" step="0.01" id="edit_rechnungsfahrten" name="rechnungsfahrten" required>

			<label for="edit_krankenfahrten"><i class="fas fa-ambulance"></i> Krankenfahrten:</label>
			<input type="number" step="0.01" id="edit_krankenfahrten" name="krankenfahrten" required>

			<label for="edit_gutscheine"><i class="fas fa-ticket-alt"></i> Gutscheine:</label>
			<input type="number" step="0.01" id="edit_gutscheine" name="gutscheine" required>

			<label for="edit_alita"><i class="fas fa-bus"></i> Alita:</label>
			<input type="number" step="0.01" id="edit_alita" name="alita" required>

			<label for="edit_tankwaschen"><i class="fas fa-gas-pump"></i> Tanken/Waschen:</label>
			<input type="number" step="0.01" id="edit_tankwaschen" name="tankwaschen" required>

			<label for="edit_sonstige"><i class="fas fa-money-bill-wave"></i> Sonstige Ausgaben:</label>
			<input type="number" step="0.01" id="edit_sonstige" name="sonstige" required>

			<label for="edit_notiz"><i class="fas fa-sticky-note"></i> Notiz:</label>
			<textarea id="edit_notiz" name="notiz" rows="3" placeholder="Hinweis oder Kommentar…"></textarea>

			<button type="submit"><i class="fas fa-save"></i> Speichern</button>
			<button type="button" onclick="closeEditModal()"><i class="fas fa-times"></i> Abbrechen</button>
		</form>
	</div>

    <!-- Modal für neuen Umsatz -->
    <div class="modal" id="addModal">
        <form action="insert_umsatz.php" method="POST">
            <h2>Neuen Umsatz erfassen</h2>
            <input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer_id) ?>">
            <label for="add_datum"><i class="fas fa-calendar-alt"></i> Datum:</label>
            <input type="date" id="add_datum" name="datum" required>
            <label for="add_taxameter"><i class="fas fa-euro-sign"></i> Taxameter:</label>
            <input type="number" step="0.01" id="add_taxameter" name="taxameter" required>
            <label for="add_ohnetaxameter"><i class="fas fa-euro-sign"></i> Ohne Taxameter:</label>
            <input type="number" step="0.01" id="add_ohnetaxameter" name="ohnetaxameter" required>
            <label for="add_kartenzahlung"><i class="fas fa-credit-card"></i> Kartenzahlung:</label>
            <input type="number" step="0.01" id="add_kartenzahlung" name="kartenzahlung" required>
            <label for="add_rechnungsfahrten"><i class="fas fa-file-invoice"></i> Rechnungsfahrten:</label>
            <input type="number" step="0.01" id="add_rechnungsfahrten" name="rechnungsfahrten" required>
            <label for="add_krankenfahrten"><i class="fas fa-ambulance"></i> Krankenfahrten:</label>
            <input type="number" step="0.01" id="add_krankenfahrten" name="krankenfahrten" required>
            <label for="add_gutscheine"><i class="fas fa-ticket-alt"></i> Gutscheine:</label>
            <input type="number" step="0.01" id="add_gutscheine" name="gutscheine" required>
            <label for="add_alita"><i class="fas fa-bus"></i> Alita:</label>
            <input type="number" step="0.01" id="add_alita" name="alita" required>
            <label for="add_tankwaschen"><i class="fas fa-gas-pump"></i> Tanken/Waschen:</label>
            <input type="number" step="0.01" id="add_tankwaschen" name="tankwaschen" required>
            <label for="add_sonstige"><i class="fas fa-money-bill-wave"></i> Sonstige Ausgaben:</label>
            <input type="number" step="0.01" id="add_sonstige" name="sonstige" required>
            <button type="submit"><i class="fas fa-save"></i> Erfassen</button>
            <button type="button" onclick="closeAddModal()"><i class="fas fa-times"></i> Abbrechen</button>
        </form>
    </div>
	
	<!-- Modal für den Änderungsverlauf -->
	<div class="modal" id="verlaufModal">
		<h2>Änderungsverlauf</h2>
		<div id="verlaufContent">Lade Verlauf...</div>
		<button onclick="closeVerlaufModal()"><i class="fas fa-times"></i> Schließen</button>
	</div>


    <!-- JavaScript für Modals -->
    <script>
        // Funktion für das Bearbeitungsmodal
        function openEditModal(button) {
            document.getElementById('edit_umsatzid').value = button.getAttribute('data-umsatzid');
            document.getElementById('edit_datum').value = button.getAttribute('data-datum');
            document.getElementById('edit_taxameter').value = button.getAttribute('data-taxameter');
            document.getElementById('edit_ohnetaxameter').value = button.getAttribute('data-ohnetaxameter');
            document.getElementById('edit_kartenzahlung').value = button.getAttribute('data-kartenzahlung');
            document.getElementById('edit_rechnungsfahrten').value = button.getAttribute('data-rechnungsfahrten');
            document.getElementById('edit_krankenfahrten').value = button.getAttribute('data-krankenfahrten');
            document.getElementById('edit_gutscheine').value = button.getAttribute('data-gutscheine');
            document.getElementById('edit_alita').value = button.getAttribute('data-alita');
            document.getElementById('edit_tankwaschen').value = button.getAttribute('data-tankwaschen');
            document.getElementById('edit_sonstige').value = button.getAttribute('data-sonstige');
            document.getElementById('editModal').classList.add('active');
            overlay.classList.add('active');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            overlay.classList.remove('active');
        }
        // Funktion für das Erfassungsmodal
        const showAddModalButton = document.getElementById('showAddModalButton');
        showAddModalButton.addEventListener('click', () => {
            document.getElementById('addModal').classList.add('active');
            overlay.classList.add('active');
        });
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            overlay.classList.remove('active');
        }

        // Burger-Menü Script (falls vorhanden)
        document.querySelector('.burger-menu')?.addEventListener('click', () => {
            document.querySelector('.nav-links').classList.toggle('active');
        });
		function confirmDelete(button) {
			const umsatzid = button.getAttribute('data-umsatzid');
			const fahrerid = button.getAttribute('data-fahrerid');
			
			if (confirm("Möchten Sie diesen Umsatz wirklich löschen?")) {
				// Erstelle ein verstecktes Formular zur Übertragung der Daten per POST
				const form = document.createElement("form");
				form.method = "POST";
				form.action = "delete_umsatz.php";
				
				const inputUmsatz = document.createElement("input");
				inputUmsatz.type = "hidden";
				inputUmsatz.name = "umsatzid";
				inputUmsatz.value = umsatzid;
				form.appendChild(inputUmsatz);
				
				const inputFahrer = document.createElement("input");
				inputFahrer.type = "hidden";
				inputFahrer.name = "fahrer_id";
				inputFahrer.value = fahrerid;
				form.appendChild(inputFahrer);
				
				document.body.appendChild(form);
				form.submit();
			}
		}
		function openVerlaufModal(umsatzid) {
			const modal = document.getElementById('verlaufModal');
			const overlay = document.getElementById('modalOverlay');
			const content = document.getElementById('verlaufContent');

			modal.classList.add('active');
			overlay.classList.add('active');
			content.innerHTML = 'Lade Verlauf...';

			fetch('umsatz_verlauf.php?umsatzid=' + encodeURIComponent(umsatzid))
				.then(response => response.text())
				.then(html => content.innerHTML = html)
				.catch(err => content.innerHTML = 'Fehler beim Laden des Verlaufs.');
		}

		function closeVerlaufModal() {
			document.getElementById('verlaufModal').classList.remove('active');
			document.getElementById('modalOverlay').classList.remove('active');
		}
    </script>
</body>
</html>
