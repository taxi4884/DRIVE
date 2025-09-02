<?php
// public/umsatz_dashboard.php

require_once '../includes/bootstrap.php';

// Zeitbereich festlegen (Standard: aktueller Monat)
$zeitraum = $_GET['zeitraum'] ?? 'aktuell';
$monat = $_GET['monat'] ?? date('m');
$jahr = $_GET['jahr'] ?? date('Y');

// SQL-Bedingungen je nach Zeitbereich
if ($zeitraum === 'letzter') {
    $monat = date('m', strtotime('-1 month'));
    $jahr = date('Y', strtotime('-1 month'));
}
$start_date = "$jahr-$monat-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Umsätze mit Firmenzugehörigkeit abrufen
$stmtUmsatz = $pdo->prepare("
	SELECT 
		companies.name AS Firma,
		f.FahrerID,
		CONCAT(f.Vorname, ' ', f.Nachname) AS FahrerName,
		u.Datum,
		SUM(u.TaxameterUmsatz + u.OhneTaxameter) AS GesamtUmsatz,
		SEC_TO_TIME(z.arbeitssekunden) AS Arbeitszeit,
		ROUND(
			SUM(u.TaxameterUmsatz + u.OhneTaxameter)
			/ GREATEST(z.arbeitssekunden / 3600, 1),
			2
		) AS UmsatzProStunde
	FROM Fahrer f
	JOIN FahrerFahrzeug ff ON f.FahrerID = ff.FahrerID
	JOIN Fahrzeuge v       ON ff.FahrzeugID = v.FahrzeugID
	JOIN companies         ON v.company_id = companies.id
	LEFT JOIN Umsatz u     ON f.FahrerID = u.FahrerID AND u.Datum BETWEEN ? AND ?
	LEFT JOIN (
		SELECT
			(sf.fahrer) AS fahrer_alias,
			DATE(sf.anmeldung) AS tag,
			SUM(TIMESTAMPDIFF(SECOND, sf.anmeldung, sf.abmeldung)) AS arbeitssekunden
		FROM sync_fahreranmeldung sf
		WHERE sf.anmeldung IS NOT NULL AND sf.abmeldung IS NOT NULL
		GROUP BY fahrer_alias, tag
	) AS z
	  ON (z.fahrer_alias = f.fms_alias OR z.fahrer_alias = f.Fahrernummer)
	 AND z.tag = u.Datum
	WHERE u.Datum IS NOT NULL
	GROUP BY companies.name, f.FahrerID, FahrerName, u.Datum, z.arbeitssekunden
	ORDER BY companies.name ASC, u.Datum ASC, f.Nachname ASC;
");
$stmtUmsatz->execute([$start_date, $end_date]);
$umsatzDaten = $stmtUmsatz->fetchAll(PDO::FETCH_ASSOC);

// Daten nach Firma gruppieren
$datenNachFirma = [];
foreach ($umsatzDaten as $eintrag) {
    $firma = $eintrag['Firma'];
    $datum = $eintrag['Datum'];
    $fahrerName = $eintrag['FahrerName'];
    $fahrerID = $eintrag['FahrerID']; // FahrerID hinzufügen
    $gesamtUmsatz = $eintrag['GesamtUmsatz'];
	$arbeitszeit = $eintrag['Arbeitszeit'];
    $umsatzProStunde = $eintrag['UmsatzProStunde'];

    if (!isset($datenNachFirma[$firma])) {
        $datenNachFirma[$firma] = [];
    }
    if (!isset($datenNachFirma[$firma][$datum])) {
        $datenNachFirma[$firma][$datum] = [];
    }
    $datenNachFirma[$firma][$datum][$fahrerName] = [
		'FahrerID'        => $fahrerID,
		'GesamtUmsatz'    => $gesamtUmsatz,
		'Arbeitszeit'     => $arbeitszeit,
		'UmsatzProStunde' => $umsatzProStunde
	];
}

// Gesamtumsatz pro Firma berechnen
$gesamtProFirma = [];
foreach ($datenNachFirma as $firma => $daten) {
    $gesamtProFirma[$firma] = array_reduce($daten, function ($carry, $datum) {
        // Nur die Umsatzwerte auslesen und summieren
        return $carry + array_sum(array_column($datum, 'GesamtUmsatz'));
    }, 0);
}

$totalFirmen = count($gesamtProFirma); // Anzahl der Firmen
$itemWidth = 200; // Breite pro Eintrag in Pixel
$totalWidth = $totalFirmen * $itemWidth; // Gesamtbreite
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umsatz nach Firma | DRIVE</title>
    <link rel="stylesheet" href="css/custom.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <main>
        <h1>Umsatz-Dashboard nach Firma</h1>
        
        <!-- Zeitraum auswählen -->
        <form method="GET" action="umsatz_dashboard.php">
            <label for="zeitraum">Zeitraum:</label>
            <select id="zeitraum" name="zeitraum" onchange="this.form.submit()">
                <option value="aktuell" <?= $zeitraum === 'aktuell' ? 'selected' : '' ?>>Aktueller Monat</option>
                <option value="letzter" <?= $zeitraum === 'letzter' ? 'selected' : '' ?>>Letzter Monat</option>
                <option value="monat" <?= $zeitraum === 'monat' ? 'selected' : '' ?>>Monat wählen</option>
            </select>
            
            <?php if ($zeitraum === 'monat'): ?>
                <select id="monat" name="monat" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $monat == $m ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select id="jahr" name="jahr" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                        <option value="<?= $y ?>" <?= $jahr == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            <?php endif; ?>
        </form>

        <!-- Gesamtumsatz nach Firma -->
        <section>
			<h2>Gesamtumsatz pro Firma</h2>
			<ul style="display: flex; overflow-x: auto">
				<?php foreach ($gesamtProFirma as $firma => $gesamtUmsatz): ?>
					<li style="flex: 0 0 <?= $itemWidth ?>px; margin: 0 10px; text-align: center;">
						<strong><?= htmlspecialchars($firma) ?>:</strong> 
						<br>
						<?= number_format($gesamtUmsatz, 2, ',', '.') ?> €
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

        <!-- Umsatz-Tabelle nach Firma -->
		<?php foreach ($datenNachFirma as $firma => $daten): ?>
			<section>
				<!-- Button zum Ein- und Ausklappen -->
				<h2>
					<button onclick="toggleVisibility('table-<?= htmlspecialchars($firma) ?>')">
						<?= htmlspecialchars($firma) ?>
					</button>
				</h2>
				<!-- Tabelle mit einer eindeutigen ID -->
				<table id="table-<?= htmlspecialchars($firma) ?>" style="display: none;">
					<thead>
						<tr>
							<th>Datum</th>
							<?php 
							// Fahrer der Firma sammeln und nach Nachnamen sortieren
							$fahrerDerFirma = [];
							foreach ($daten as $datum => $fahrerDaten) {
								foreach ($fahrerDaten as $fahrerName => $info) {
									if (!isset($fahrerDerFirma[$fahrerName])) {
										$fahrerDerFirma[$fahrerName] = $info['FahrerID']; // FahrerID speichern
									}
								}
							}

							// Fahrer nach Nachname sortieren
							uksort($fahrerDerFirma, function($a, $b) {
								return strcmp(explode(' ', $a)[1] ?? '', explode(' ', $b)[1] ?? '');
							});

							foreach ($fahrerDerFirma as $fahrer => $fahrerID): ?>
								<th>
									<a href="/fahrer_umsatz.php?fahrer_id=<?= urlencode($fahrerID) ?>">
										<?= htmlspecialchars($fahrer) ?>
									</a>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($daten as $datum => $fahrerDaten): ?>
							<?php if ($datum === '1970-01-01' || empty($fahrerDaten)) continue; // Zeile überspringen, wenn 01.01.1970 ?>
							
							<tr>
								<td><?= date('d.m.Y', strtotime($datum)) ?></td>
								<?php foreach ($fahrerDerFirma as $fahrer => $fahrerID): ?>
									<td>
										<?= number_format($fahrerDaten[$fahrer]['GesamtUmsatz'], 2, ',', '.') ?> €
										<br>
										<small>
											<?= $fahrerDaten[$fahrer]['Arbeitszeit'] ?? '-' ?> /
											<?= isset($fahrerDaten[$fahrer]['UmsatzProStunde']) ? number_format($fahrerDaten[$fahrer]['UmsatzProStunde'], 2, ',', '.') . ' €/h' : '-' ?>
										</small>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th>Summe</th>
							<?php foreach ($fahrerDerFirma as $fahrer => $fahrerID): ?>
								<?php
									$gesamtUmsatz = 0;
									$gesamtSekunden = 0;

									foreach ($daten as $datum => $fDaten) {
										if (!isset($fDaten[$fahrer])) continue;
										$gesamtUmsatz += $fDaten[$fahrer]['GesamtUmsatz'];

										$zeit = $fDaten[$fahrer]['Arbeitszeit'];
										if (preg_match('/(\d+):(\d+):(\d+)/', $zeit, $match)) {
											$gesamtSekunden += ($match[1] * 3600) + ($match[2] * 60) + $match[3];
										}
									}

									$umsatzProStunde = $gesamtSekunden > 0
										? round($gesamtUmsatz / ($gesamtSekunden / 3600), 2)
										: 0;

									$stunden = floor($gesamtSekunden / 3600);
									$minuten = floor(($gesamtSekunden % 3600) / 60);
									$sekunden = $gesamtSekunden % 60;
									$arbeitszeitFormatted = sprintf('%02d:%02d:%02d', $stunden, $minuten, $sekunden);
								?>
								<td>
									<strong><?= number_format($gesamtUmsatz, 2, ',', '.') ?> €</strong><br>
									<small><?= $arbeitszeitFormatted ?> / <?= number_format($umsatzProStunde, 2, ',', '.') ?> €/h</small>
								</td>
							<?php endforeach; ?>
						</tr>
					</tfoot>
				</table>
			</section>
		<?php endforeach; ?>
    </main>
	<script>
	function toggleVisibility(tableId) {
		const table = document.getElementById(tableId);
		if (table.style.display === "none") {
			table.style.display = "table"; // Sichtbar machen
		} else {
			table.style.display = "none"; // Ausblenden
		}
	}
	</script>
</body>
</html>
