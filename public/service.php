<?php
// service.php
// Verbindung zur Datenbank herstellen
require_once '../includes/bootstrap.php'; // Inklusive Authentifizierung und PDO-Datenbankverbindung

// Daten aus der Tabelle Wartung abrufen
$query = "
    SELECT 
        Wartung.WartungID, 
        Wartung.FahrzeugID, 
        Wartung.Wartungsdatum, 
        Wartung.Kilometerstand, 
        Wartung.Beschreibung, 
        Wartung.Kosten, 
        Wartung.Werkstatt, 
        Wartung.Bemerkungen,
        Fahrzeuge.Konzessionsnummer, 
        Fahrzeuge.Marke, 
        Fahrzeuge.Modell
    FROM Wartung
    JOIN Fahrzeuge ON Wartung.FahrzeugID = Fahrzeuge.FahrzeugID
    ORDER BY Wartung.Wartungsdatum DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $wartungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartungen | DRIVE</title>
    <link rel="stylesheet" href="css/custom.css">
    <script src="js/modal.js"></script>
</head>
<body>
    <?php include 'nav.php'; ?>
    <main>
        <h1>Wartungen</h1>
		
		<button class="btn" onclick="openModal('maintenanceModal')">Wartungstermin</button>
		
        <table>
            <thead>
                <tr>
                    <th>Fahrzeug</th>
                    <th>Wartungsdatum</th>
                    <th>Kilometerstand</th>
                    <th>Beschreibung</th>
                    <th>Kosten (€)</th>
                    <th>Werkstatt</th>
                    <th>Bemerkungen</th>
                </tr>
            </thead>
			<tbody>
				<?php if (!empty($wartungen)): ?>
					<?php foreach ($wartungen as $wartung): ?>
						<tr onclick="window.location.href='edit_service.php?id=<?= htmlspecialchars($wartung['WartungID']) ?>'">
							<td>
								<?= htmlspecialchars($wartung['Konzessionsnummer']) ?> - 
								<?= htmlspecialchars($wartung['Marke']) ?> 
								<?= htmlspecialchars($wartung['Modell']) ?>
							</td>
							<td><?= htmlspecialchars(date('d.m.Y', strtotime($wartung['Wartungsdatum']))) ?></td>
							<td><?= htmlspecialchars(number_format($wartung['Kilometerstand'], 0, ',', '.')) ?> km</td>
							<td><?= htmlspecialchars($wartung['Beschreibung'] ?? 'Keine Beschreibung') ?></td>
							<td><?= htmlspecialchars(number_format($wartung['Kosten'], 2, ',', '.')) ?> €</td>
							<td><?= htmlspecialchars($wartung['Werkstatt']) ?></td>
							<td><?= nl2br(htmlspecialchars($wartung['Bemerkungen'] ?? 'Keine Bemerkungen')) ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr>
						<td colspan="7">Keine Wartungsdaten gefunden.</td>
					</tr>
				<?php endif; ?>
			</tbody>
        </table>
    </main>
	
        <?php include 'modals/add_maintanance_modal.php'; ?>
        <script>
        document.querySelector('.burger-menu').addEventListener('click', () => {
            document.querySelector('.nav-links').classList.toggle('active');
        });
    </script>
</body>
</html>
