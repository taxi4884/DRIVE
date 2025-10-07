<?php
require_once '../includes/bootstrap.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

$driversCacheKey = 'drivers_all';
$usersCacheKey = 'benutzer_all';
$permissionsCacheKey = 'message_permissions_matrix';
$cacheTtl = 900; // 15 Minuten

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

    // Cache invalidieren, damit Änderungen sofort sichtbar sind
    Cache::delete($driversCacheKey);
    Cache::delete($usersCacheKey);
    Cache::delete($permissionsCacheKey);
}

// Fetch all drivers
$drivers = Cache::remember($driversCacheKey, function () use ($pdo) {
    $driversStmt = $pdo->query('SELECT FahrerID, CONCAT(Vorname, " ", Nachname) AS name FROM Fahrer ORDER BY Nachname, Vorname');
    return $driversStmt->fetchAll(PDO::FETCH_ASSOC);
}, $cacheTtl);

// Fetch all benutzer recipients
$users = Cache::remember($usersCacheKey, function () use ($pdo) {
    $usersStmt = $pdo->query('SELECT BenutzerID, Name FROM Benutzer ORDER BY Name');
    return $usersStmt->fetchAll(PDO::FETCH_ASSOC);
}, $cacheTtl);

// Fetch existing permissions
$permissions = Cache::remember($permissionsCacheKey, function () use ($pdo) {
    $permStmt = $pdo->query('SELECT driver_id, recipient_id FROM message_permissions');
    $result = [];
    while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
        $driverId = (int) $row['driver_id'];
        $recipientId = (int) $row['recipient_id'];
        $result[$driverId][] = $recipientId;
    }
    return $result;
}, $cacheTtl);

$title = 'Nachrichtenberechtigungen';
include '../includes/layout.php';
?>
<div class="container my-4">
    <h1>Nachrichtenberechtigungen</h1>
    <form method="post">
        <table class="table">
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
                        <select name="recipients[<?= $driver['FahrerID'] ?>][]" multiple size="5" class="form-select">
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
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
</div>
</body>
</html>
