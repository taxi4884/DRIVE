<?php
//public/driver/dashboard.php
require_once '../../includes/bootstrap.php';

// Rolle für diese Route festlegen (einfachste Variante)
$_SESSION['rolle'] = 'Fahrer';

// Fehleranzeige aktivieren (nur für Debugging, in Produktion entfernen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session überprüfen
if (!isset($_SESSION['user_id'])) {
    die('Fehler: Keine gültige Session. Bitte erneut anmelden.');
}

$fahrer_id = $_SESSION['user_id'];

// Fahrer abfragen
$stmt = $pdo->prepare("SELECT * FROM Fahrer WHERE FahrerID = ?");
if (!$stmt->execute([$fahrer_id])) {
    $errorInfo = $stmt->errorInfo();
    die('SQL-Fehler: ' . $errorInfo[2]);
}

$fahrer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fahrer) {
    $fahrer = ['Vorname' => 'Unbekannter', 'Nachname' => 'Benutzer'];
}

// ────────────────────────────────────────────────────────────
// P-Schein-Gültigkeit prüfen
// ────────────────────────────────────────────────────────────
$pscheinGueltigkeit = $fahrer['PScheinGueltigkeit'] ?? null;
$pscheinHinweis = '';
if ($pscheinGueltigkeit) {
    // Datum in einen Timestamp umwandeln
    $pscheinTimestamp = strtotime($pscheinGueltigkeit);
    // Aktueller Zeitpunkt plus 3 Monate
    $inDreiMonaten = strtotime('+3 months');

    if ($pscheinTimestamp <= $inDreiMonaten) {
        // Formatierung in dd.mm.yy
        $pscheinFormat = date('d.m.y', $pscheinTimestamp);
        
        // Anzahl der verbleibenden Tage berechnen
        $tageBisAblauf = ceil(($pscheinTimestamp - time()) / 86400); // 86400 Sekunden = 1 Tag
        
        if ($tageBisAblauf <= 0) {
            // Schon abgelaufen
            $pscheinHinweis = "<div class='pschein-warnung'>
								<i class='fa-solid fa-circle-exclamation warn-icon'></i>
                                </i><strong>Achtung:</strong> Dein P-Schein ist bereits abgelaufen (gültig bis: $pscheinFormat)!
                               </div>";
        } else {
            // Läuft in weniger als 3 Monaten ab
            $pscheinHinweis = "<div class='pschein-warnung'>
								<i class='fa-solid fa-circle-exclamation warn-icon'></i>
                                </i><strong>Achtung:</strong> Dein P-Schein läuft in $tageBisAblauf Tagen ab (gültig bis: $pscheinFormat)!
                               </div>";
        }
    }
}
// ────────────────────────────────────────────────────────────

// Zeitraum basierend auf der Auswahl setzen
$zeitraum = $_GET['zeitraum'] ?? 'monat';
$start_date = null;
$end_date = null;

if ($zeitraum === 'monat') {
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-t');

} elseif ($zeitraum === 'woche') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date   = date('Y-m-d', strtotime('sunday this week'));

} elseif ($zeitraum === 'letzte_woche') {                 // ➊ NEU
    $start_date = date('Y-m-d', strtotime('monday last week'));
    $end_date   = date('Y-m-d', strtotime('sunday last week'));

} elseif ($zeitraum === 'tag') {
    $start_date = $end_date = date('Y-m-d');

} elseif ($zeitraum === 'individuell') {
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date']   ?? null;

    if (!$start_date || !$end_date) {
        die('Bitte Start- und Enddatum eingeben.');
    }

} else {                                                  // Fallback
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-t');
}


$stmt = $pdo->prepare("
    SELECT 
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
        Abgerechnet
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
    ORDER BY Datum ASC
");

if (!$stmt->execute([$fahrer_id, $start_date, $end_date])) {
    $errorInfo = $stmt->errorInfo();
    die('SQL-Fehler: ' . $errorInfo[2]);
}

$umsatzDaten = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gesamtUmsatz = 0;
$gesamtBargeld = 0;

foreach ($umsatzDaten as $eintrag) {
    $umsatz = $eintrag['TaxameterUmsatz'] + $eintrag['OhneTaxameter'];
    $ausgaben = $eintrag['Kartenzahlung'] + $eintrag['Rechnungsfahrten'] + $eintrag['Krankenfahrten'] + $eintrag['Gutscheine'] + $eintrag['Alita'] + $eintrag['TankenWaschen'] + $eintrag['SonstigeAusgaben'];
    $bargeld = $umsatz - $ausgaben;

    $gesamtUmsatz += $umsatz;
    $gesamtBargeld += $bargeld;
}

// Nächste geplante Abrechnung anzeigen, wenn vorhanden
$stmtAbrechnung = $pdo->query("
    SELECT Datum, Uhrzeit
    FROM Abrechnungsplanung
    WHERE Datum >= CURDATE()
    ORDER BY Datum ASC
    LIMIT 1
");
$naechsteAbrechnung = $stmtAbrechnung->fetch(PDO::FETCH_ASSOC);

// Mitteilung an alle Fahrer abrufen
$stmtHinweis = $pdo->query("SELECT * FROM fahrer_mitteilungen WHERE sichtbar = TRUE AND gueltig_bis >= CURDATE() ORDER BY erstellt_am DESC LIMIT 1");
$fahrerHinweis = $stmtHinweis->fetch(PDO::FETCH_ASSOC);

$title = 'Fahrer Dashboard';
$extraCss = [
    'css/driver-dashboard.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'
];
include __DIR__ . '/../../includes/layout.php';
?>
        <style>
	/* Grundlayout */
	body {
		font-family: 'Arial', sans-serif;
		background: #f4f6f8;
		margin: 0;
		padding: 0;
	}

	main {
		padding: 20px;
	}

	/* Überschriften */
	h1 {
		font-size: 2rem;
		color: #333;
		margin-bottom: 1.5rem;
		text-align: center;
	}

	h2 {
		font-size: 1.6rem;
		color: #333;
		margin-bottom: 1rem;
	}

	/* Warnboxen */
	.pschein-warnung, .hinweis-box {
		background-color: #fff9d6;
		border: 1px solid #ffcc00;
		color: #856404;
		padding: 15px;
		border-radius: 8px;
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 1.5rem;
		font-size: 0.95rem;
	}

	.warn-icon, .hinweis-icon {
		font-size: 2rem;
		color: #ffcc00;
	}

	.hinweis-text {
		font-size: 1rem;
		line-height: 1.5;
	}

	.hinweis-text strong {
		color: #856404;
	}

	/* Umsatzbereich */
	section {
		background: #ffffff;
		padding: 20px;
		border-radius: 12px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
		margin-top: 20px;
	}

	/* Zeitraum-Formular */
	form {
		margin-bottom: 20px;
	}

	form label {
		font-weight: bold;
		display: block;
		margin-bottom: 5px;
		color: #555;
	}

	form select, 
	form input[type="date"],
	form button {
		width: 100%;
		padding: 10px;
		margin-top: 5px;
		border: 1px solid #ccc;
		border-radius: 6px;
		box-sizing: border-box;
	}

	form button {
		background-color: #007bff;
		color: white;
		font-weight: bold;
		cursor: pointer;
		transition: background-color 0.3s;
	}

	form button:hover {
		background-color: #0056b3;
	}

	/* Umsatzkarten */
	.gesamt-container {
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		gap: 15px;
		margin-bottom: 20px;
	}

	.gesamt-container .card {
		flex: 1 1 150px;
		background: #f9fafb;
		padding: 20px;
		border-radius: 12px;
		border: 1px solid #ddd;
		text-align: center;
		box-shadow: 0 2px 5px rgba(0,0,0,0.05);
		font-size: 1rem;
		transition: transform 0.2s;
	}

	.gesamt-container .card:hover {
		transform: translateY(-3px);
	}

	.card.umsatz {
		background: #e0f0ff;
		border-color: #b8e0ff;
	}

	.card.bargeld {
		background: #e7f9ed;
		border-color: #b2f2bb;
	}

	.card .icon {
		font-size: 2rem;
		margin-bottom: 8px;
		display: block;
	}

	.card-title {
		font-weight: bold;
		margin-bottom: 5px;
		color: #333;
	}

	.card-value {
		font-size: 1.4rem;
		font-weight: bold;
	}

	/* Umsatz-Tabelle */
	.umsatz-tabelle {
		width: 100%;
		border-collapse: collapse;
		margin-top: 20px;
	}

	.umsatz-tabelle th {
		background-color: #007bff;
		color: white;
		padding: 12px;
		text-align: center;
	}

	.umsatz-tabelle td {
		background: white;
		padding: 12px 8px;
		text-align: center;
		vertical-align: middle;
		border-bottom: 1px solid #ddd;
	}

	/* Umsatz- und Bargeldspalten */
	.umsatz-tabelle td[data-label="Gesamtumsatz"] {
		background: #e0f0ff;
		font-weight: bold;
		color: #004085;
		border-radius: 6px;
	}

	.umsatz-tabelle td[data-label="Bargeld"] {
		background: #e7f9ed;
		font-weight: bold;
		color: #155724;
		border-radius: 6px;
	}

	/* Tabellen-Zeilen Hover */
	.umsatz-tabelle tr:hover td {
		background: #f1f3f5;
	}

	/* Aktionen */
	.actions {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 5px;
	}

	.edit-btn, .delete-btn {
		text-decoration: none;
		font-weight: bold;
		padding: 6px 10px;
		border-radius: 6px;
		transition: background 0.2s;
	}

	.edit-btn {
		background-color: #cce5ff;
		color: #004085;
	}

	.edit-btn:hover {
		background-color: #99ccff;
	}

	.delete-btn {
		background-color: #f8d7da;
		color: #721c24;
	}

	.delete-btn:hover {
		background-color: #f5b7b9;
	}

	/* Button Umsatz erfassen */
	.buttons {
		text-align: center;
		margin-top: 10px;
	}

	.buttons .btn {
		display: inline-block;
		background-color: #28a745;
		color: white;
		font-weight: bold;
		padding: 10px 20px;
		border-radius: 6px;
		text-decoration: none;
		transition: background-color 0.3s;
	}

	.buttons .btn:hover {
		background-color: #218838;
	}

	/* Link zur Statistik */
	a[href="statistics.php"] {
		display: block;
		text-align: center;
		margin-top: 20px;
		font-weight: bold;
		color: #007bff;
		text-decoration: none;
	}

	a[href="statistics.php"]:hover {
		text-decoration: underline;
	}
        </style>

    <main>
        <h1>Willkommen, <?= htmlspecialchars($fahrer['Vorname'] ?? 'Unbekannt') ?></h1>
		
        <!-- P-Schein Hinweis anzeigen, falls vorhanden -->
        <?= $pscheinHinweis ?>
		
		<?php if ($naechsteAbrechnung): ?>
			<div class="hinweis-box">
				<div class="hinweis-icon">
					<i class="fas fa-calendar-check"></i>
				</div>
				<div class="hinweis-text">
					<strong>Info:</strong><br>
					Yannik plant am <strong><?= date('d.m.Y', strtotime($naechsteAbrechnung['Datum'])) ?></strong><br>
					gegen <strong><?= htmlspecialchars($naechsteAbrechnung['Uhrzeit']) ?> Uhr</strong> zur Abrechnung zu kommen.
				</div>
			</div>
		<?php endif; ?>
		
		<?php if (!empty($fahrerHinweis)): ?>
			<div class="hinweis-box" style="background-color: <?= $fahrerHinweis['wichtig'] ? '#ffe4e4' : '#fff9d6' ?>; border-color: <?= $fahrerHinweis['wichtig'] ? '#cc0000' : '#ffcc00' ?>;">
				<div class="hinweis-icon">
					<i class="fas fa-bullhorn" style="color: <?= $fahrerHinweis['wichtig'] ? '#cc0000' : '#ffcc00' ?>;"></i>
				</div>
				<div class="hinweis-text">
					<strong>Chrissi informiert:</strong><br>
					<?= nl2br(htmlspecialchars($fahrerHinweis['nachricht'])) ?>
				</div>
			</div>
		<?php endif; ?>
		
        <p>Hier findest du eine Übersicht deiner Umsätze.</p>
        
        <section class="card mb-3">
            <div class="card-body">
            <h2>Umsatzübersicht</h2>
            <form method="GET" action="dashboard.php">
                <label for="zeitraum">Zeitraum:</label>
                <select id="zeitraum" name="zeitraum" onchange="toggleIndividuellFields(); this.form.submit();">
                    <option value="monat" <?= $zeitraum === 'monat' ? 'selected' : '' ?>>Aktueller Monat</option>
					<option value="letzte_woche" <?= $zeitraum === 'letzte_woche' ? 'selected' : '' ?>>Letzte Woche</option>
                    <option value="woche" <?= $zeitraum === 'woche' ? 'selected' : '' ?>>Aktuelle Woche</option>
                    <option value="tag" <?= $zeitraum === 'tag' ? 'selected' : '' ?>>Heute</option>
                    <option value="individuell" <?= $zeitraum === 'individuell' ? 'selected' : '' ?>>Individueller Zeitraum</option>
                </select>
                
                <div id="individuell-fields" style="display: <?= $zeitraum === 'individuell' ? 'block' : 'none' ?>;">
                    <label for="start_date">Startdatum:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date ?? '') ?>">
                    
                    <label for="end_date">Enddatum:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date ?? '') ?>">
                    
                    <button type="submit">Anzeigen</button>
                </div>
            </form>
			<div class="gesamt-container">
				<div class="card umsatz">
					<span class="icon">📈</span>
					<div class="card-title">Gesamtumsatz</div>
					<div class="card-value"><?= number_format($gesamtUmsatz, 2, ',', '.') ?> €</div>
				</div>
				<div class="card bargeld">
					<span class="icon">💸</span>
					<div class="card-title">Bargeld</div>
					<div class="card-value"><?= number_format($gesamtBargeld, 2, ',', '.') ?> €</div>
				</div>
			</div>
            <table class="umsatz-tabelle">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Gesamtumsatz (€)</th>
                        <th>Bargeld (€)</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($umsatzDaten)): ?>
                        <?php foreach ($umsatzDaten as $eintrag): ?>
                            <?php 
                                $umsatz = $eintrag['TaxameterUmsatz'] + $eintrag['OhneTaxameter'];
                                $ausgaben = $eintrag['Kartenzahlung'] + $eintrag['Rechnungsfahrten'] + $eintrag['Krankenfahrten'] + $eintrag['Gutscheine'] + $eintrag['Alita'] + $eintrag['TankenWaschen'] + $eintrag['SonstigeAusgaben'];
                                $bargeld = $umsatz - $ausgaben;
                            ?>
                            <tr>
                                <td data-label="Datum"><?= htmlspecialchars(DateTime::createFromFormat('Y-m-d', $eintrag['Datum'])->format('d.m.Y')) ?></td>
                                <td data-label="Gesamtumsatz"><?= number_format($umsatz, 2, ',', '.') ?> €</td>
                                <td data-label="Bargeld"><?= number_format($bargeld, 2, ',', '.') ?> €</td>
                                <td class="actions">
									<?php if ($eintrag['Abgerechnet'] == 0): ?>
										<a href="update_entry.php?datum=<?= urlencode($eintrag['Datum']) ?>" class="edit-btn">✏️ Bearbeiten</a>
										<a href="delete_entry.php?datum=<?= urlencode($eintrag['Datum']) ?>" class="delete-btn" onclick="return confirm('Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?');">🗑️ Löschen</a>
									<?php else: ?>
										<span style="color: gray;">✅ Abgerechnet</span>
									<?php endif; ?>
								</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Keine Umsätze für den gewählten Zeitraum.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
                        <a href="statistics.php">Statistik</a>
            </div>
        </section>
    </main>
	<script>
        // Zeigt oder versteckt die Felder für den individuellen Zeitraum
        function toggleIndividuellFields() {
            const zeitraum = document.getElementById('zeitraum').value;
            const individuellFields = document.getElementById('individuell-fields');
            individuellFields.style.display = (zeitraum === 'individuell') ? 'block' : 'none';
        }
    </script>
</body>
</html>
