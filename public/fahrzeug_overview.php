<?php
require_once '../includes/bootstrap.php';
require_once 'modals/process_vehicle.php';

// Fahrzeuge abrufen
$stmt = $pdo->query("SELECT * FROM Fahrzeuge ORDER BY Konzessionsnummer ASC");
$fahrzeuge = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrzeugübersicht | DRIVE</title>
    <link rel="stylesheet" href="css/custom.css">
    <script src="js/modal.js"></script>
</head>
<body>
    <?php include 'nav.php'; ?>
    <main>
        <h1>Fahrzeugübersicht</h1>
        <div class="button-group">
            <button class="btn" onclick="openModal('vehicleModal')">Neues Fahrzeug hinzufügen</button>
        </div>
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Marke</th>
                    <th>Modell</th>
                    <th>Konzession</th>
                    <th>Typ</th>
                    <th>HU</th>
                    <th>Eichung</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($fahrzeuge)): ?>
                    <?php foreach ($fahrzeuge as $fahrzeug): ?>
                        <tr>
                            <td><?= htmlspecialchars($fahrzeug['Marke']) ?></td>
                            <td><?= htmlspecialchars($fahrzeug['Modell']) ?></td>
                            <td><?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?></td>
                            <td><?= htmlspecialchars($fahrzeug['Typ']) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y', strtotime($fahrzeug['HU']))) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y', strtotime($fahrzeug['Eichung']))) ?></td>
                            <td>
                                <a href="fahrzeug_bearbeiten.php?id=<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>" class="btn-sm">Bearbeiten</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">Keine Fahrzeuge gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
    <?php include 'modals/add_vehicle_modal.php'; ?>
</body>
</html>
