<?php
require_once '../includes/bootstrap.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['recipients'] ?? [] as $driverId => $recipientIds) {
        $driverId = (int)$driverId;
        // Remove existing permissions for driver
        $deleteStmt = $pdo->prepare('DELETE FROM message_permissions WHERE driver_id = ?');
        $deleteStmt->execute([$driverId]);

        // Insert new permissions
        if (!empty($recipientIds)) {
            $insertStmt = $pdo->prepare('INSERT INTO message_permissions (driver_id, recipient_id) VALUES (:driver_id, :recipient_id)');
            foreach ($recipientIds as $recipientId) {
                $insertStmt->execute([
                    'driver_id' => $driverId,
                    'recipient_id' => (int)$recipientId
                ]);
            }
        }
    }
}

// Fetch all drivers
$driversStmt = $pdo->query('SELECT FahrerID, CONCAT(Vorname, " ", Nachname) AS name FROM Fahrer ORDER BY Nachname, Vorname');
$drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all benutzer recipients
$usersStmt = $pdo->query('SELECT BenutzerID, Name FROM Benutzer ORDER BY Name');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing permissions
$permStmt = $pdo->query('SELECT driver_id, recipient_id FROM message_permissions');
$permissions = [];
while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
    $permissions[$row['driver_id']][] = $row['recipient_id'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Nachrichtenberechtigungen</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="wrapper">
    <h1>Nachrichtenberechtigungen</h1>
    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Fahrer</th>
                    <th>Empfänger (Benutzer)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($drivers as $driver): ?>
                <tr>
                    <td><?= htmlspecialchars($driver['name']) ?></td>
                    <td>
                        <select name="recipients[<?= $driver['FahrerID'] ?>][]" multiple size="5">
                            <?php
                            $selected = $permissions[$driver['FahrerID']] ?? [];
                            foreach ($users as $user):
                                $isSelected = in_array($user['BenutzerID'], $selected) ? 'selected' : '';
                            ?>
                                <option value="<?= $user['BenutzerID'] ?>" <?= $isSelected ?>><?= htmlspecialchars($user['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit">Speichern</button>
    </form>
</div>
</body>
</html>
