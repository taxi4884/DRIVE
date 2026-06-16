<?php
// Verbindung zur Datenbank herstellen
require_once '../includes/bootstrap.php'; // Verbindung und Authentifizierung

// FahrzeugÜbergaben aus der Datenbank abrufen
$query = "
    SELECT 
        FahrzeugUebergabe.UebergabeDatum AS transfer_date,
        Fahrzeuge.Konzessionsnummer AS konzession,
        Fahrzeuge.Marke AS marke,
        Fahrzeuge.Modell AS modell,
        Fahrer.Vorname AS fahrer_vorname,
        Fahrer.Nachname AS fahrer_nachname,
		FahrzeugUebergabe.Kilometerstand AS kilometerstand,
        FahrzeugUebergabe.Bemerkungen AS bemerkungen
    FROM FahrzeugUebergabe
    JOIN Fahrzeuge ON FahrzeugUebergabe.FahrzeugID = Fahrzeuge.FahrzeugID
    JOIN Fahrer ON FahrzeugUebergabe.FahrerID = Fahrer.FahrerID
    ORDER BY FahrzeugUebergabe.UebergabeDatum DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}
?>
<?php
$title = 'FahrzeugÜbergaben';
include __DIR__ . '/../includes/layout.php';
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

        <main>
        <h1>FahrzeugÜbergaben</h1>

        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Fahrer</th>
                    <th>Fahrzeug</th>
					<th>Kilometerstand</th>
                    <th>Bemerkungen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transfers)): ?>
                    <?php foreach ($transfers as $transfer): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d.m.y', strtotime($transfer['transfer_date']))) ?></td>
                            <td><?= htmlspecialchars($transfer['fahrer_vorname'] . ' ' . $transfer['fahrer_nachname']) ?></td>
                            <td><?= htmlspecialchars($transfer['konzession'] . ' - ' . $transfer['marke'] . ' ' . $transfer['modell']) ?></td>
                            <td><?= htmlspecialchars($transfer['kilometerstand'] ?? 'Kein Eintrag') ?></td>
							<td><?= htmlspecialchars($transfer['bemerkungen'] ?? 'Keine Bemerkungen') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Keine Übergaben gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

</body>
</html>
