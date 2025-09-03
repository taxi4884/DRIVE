<?php
require_once '../includes/bootstrap.php';
require_once 'modals/process_driver.php';
require_once 'modals/process_vehicle.php';
require_once 'modals/process_maintenance.php';
require_once 'modals/process_transfer.php';
require_once 'modals/process_control.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = '';
$success = '';

// Fahrer abrufen (für das Dropdown-Menü)
$stmt = $pdo->query("SELECT FahrerID, CONCAT(Vorname, ' ', Nachname) AS Name FROM Fahrer");
$fahrer = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fahrzeuge abrufen mit numerischer Sortierung der Konzessionsnummer, getrennt nach Firma
$stmt = $pdo->query("
    SELECT c.name AS Firma, f.FahrzeugID, f.Konzessionsnummer, f.HU, f.Eichung, f.Typ, f.Fahrzeugtyp, f.Kennzeichen,
           GROUP_CONCAT(CASE WHEN ff.Schicht = 'Tag' THEN CONCAT(d.Vorname, ' ', d.Nachname) END SEPARATOR ', ') AS Tagfahrer,
           GROUP_CONCAT(CASE WHEN ff.Schicht = 'Nacht' THEN CONCAT(d.Vorname, ' ', d.Nachname) END SEPARATOR ', ') AS Nachtfahrer
    FROM Fahrzeuge f
    LEFT JOIN companies c ON f.company_id = c.id
    LEFT JOIN FahrerFahrzeug ff ON f.FahrzeugID = ff.FahrzeugID
    LEFT JOIN Fahrer d ON ff.FahrerID = d.FahrerID
    GROUP BY c.id, f.FahrzeugID
    ORDER BY c.name, CAST(f.Konzessionsnummer AS UNSIGNED) ASC
");
$fahrzeuge_nach_firma = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fahrzeuge mit bald fälligem TÜV (HU)
$stmt = $pdo->prepare("
    SELECT FahrzeugID, Konzessionsnummer, Marke, Modell, HU 
    FROM Fahrzeuge 
    WHERE HU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) 
    ORDER BY HU ASC
");
$stmt->execute();
$fahrzeuge_hu = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fahrzeuge mit bald fälliger Eichung
$stmt = $pdo->prepare("
    SELECT FahrzeugID, Konzessionsnummer, Marke, Modell, Eichung 
    FROM Fahrzeuge 
    WHERE Eichung BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) 
    ORDER BY Eichung ASC
");
$stmt->execute();
$fahrzeuge_eichung = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fahrer mit bald ablaufendem P-Schein
$stmt = $pdo->prepare("
    SELECT FahrerID, CONCAT(Vorname, ' ', Nachname) AS Name, PScheinGueltigkeit 
    FROM Fahrer 
    WHERE PScheinGueltigkeit BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) 
    ORDER BY PScheinGueltigkeit ASC
");
$stmt->execute();
$fahrer_pschein = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Abfrage Wartungstermine je Fahrzeuge
$stmt = $pdo->prepare("
    SELECT w.Wartungsdatum, w.Beschreibung, w.Werkstatt, 
           f.Konzessionsnummer, f.Marke, f.Modell
    FROM Wartung w
    JOIN Fahrzeuge f ON w.FahrzeugID = f.FahrzeugID
    WHERE w.Wartungsdatum BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH)
    ORDER BY w.Wartungsdatum ASC
");
$stmt->execute();
$wartung_kommend = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$title = 'Fahrzeuge';
include __DIR__ . '/../includes/layout.php';
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="js/modal.js"></script>
    <style>
        .fahrzeug.limousine {
            background-color: #d4edda; /* Leichtes Grün */
        }
        .fahrzeug.kombi {
            background-color: #d1ecf1; /* Blau */
        }
        .fahrzeug.großraum {
            background-color: #f8d7da; /* Rot */
        }
    </style>

	    <main class="with_sidebar">
		<h1><i class="fas fa-taxi"></i> Fahrzeugbesetzung</h1>
		<?php include 'buttons.php'; ?>
		
		<?php
		$currentFirma = '';
		foreach ($fahrzeuge_nach_firma as $fahrzeug):
			if ($currentFirma !== $fahrzeug['Firma']):
				if ($currentFirma !== ''): ?>
					</tbody>
					</table>
				<?php endif; ?>
				<h2><?= htmlspecialchars($fahrzeug['Firma']) ?></h2>
				<table>
					<thead>
						<tr>
							<th>Konzession</th>
							<th>HU</th>
							<th>Eichung</th>
							<th>Tagfahrer</th>
							<th>Nachtfahrer</th>
						</tr>
					</thead>
					<tbody>
				<?php 
				$currentFirma = $fahrzeug['Firma'];
			endif; ?>
			<tr class="fahrzeug <?= strtolower($fahrzeug['Fahrzeugtyp']) ?>">
				<td><?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?> <small><?= htmlspecialchars($fahrzeug['Kennzeichen']) ?></small></td>
				<td><?= htmlspecialchars(date('d.m.Y', strtotime($fahrzeug['HU']))) ?></td>
				<td><?= htmlspecialchars(date('d.m.Y', strtotime($fahrzeug['Eichung']))) ?></td>
				<td><?= htmlspecialchars($fahrzeug['Tagfahrer'] ?? '-') ?></td>
				<td><?= htmlspecialchars($fahrzeug['Nachtfahrer'] ?? '-') ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		</table>
	</main>

	
	<!-- Sidebar -->
    <aside class="sidebar">
		<section>
			<h3>TÜV fällig <small>(nächste 3 Monate)</small></h3>
			<ul>
				<?php foreach ($fahrzeuge_hu as $fahrzeug): ?>
					<li>
						<?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?> - 
						<?= htmlspecialchars($fahrzeug['Marke']) ?> <?= htmlspecialchars($fahrzeug['Modell']) ?> 
						(<?= htmlspecialchars(date('d.m.Y', strtotime($fahrzeug['HU']))) ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section>
			<h3>Eichung fällig <small>(nächste 3 Monate)</small></h3>
			<ul>
				<?php foreach ($fahrzeuge_eichung as $fahrzeug): ?>
					<li>
						<?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?> - 
						<?= htmlspecialchars($fahrzeug['Marke']) ?> <?= htmlspecialchars($fahrzeug['Modell']) ?> 
						(<?= htmlspecialchars(date('d.m.Y', strtotime($fahrzeug['Eichung']))) ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section>
			<h3>P-Schein fällig <small>(nächste 3 Monate)</small></h3>
			<ul>
				<?php foreach ($fahrer_pschein as $fahrer): ?>
					<li>
						<?= htmlspecialchars($fahrer['Name']) ?> 
						(<?= htmlspecialchars(date('d.m.Y', strtotime($fahrer['PScheinGueltigkeit']))) ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section>
			<h3>Wartung fällig <small>(nächste 3 Monate)</small></h3>
			<ul>
				<?php foreach ($wartung_kommend as $wartung): ?>
					<li>
						<?= htmlspecialchars($wartung['Konzessionsnummer']) ?> - 
						<?= htmlspecialchars($wartung['Marke']) ?> <?= htmlspecialchars($wartung['Modell']) ?>: 
						<?= htmlspecialchars($wartung['Werkstatt']) ?> 
						(<?= htmlspecialchars(date('d.m.Y', strtotime($wartung['Wartungsdatum']))) ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</aside>


    <?php include 'modals/modals.php'; ?>

    <script>
                document.querySelector('.burger-menu').addEventListener('click', () => {
                        document.querySelector('.nav-links').classList.toggle('active');
                });
    </script>

</body>
</html>
