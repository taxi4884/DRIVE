<?php
require_once '../includes/bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

// Sicherstellen, dass Tabelle für manuelle Zeitbuchungen existiert
$pdo->exec("CREATE TABLE IF NOT EXISTS zeit_manual_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    actor_user_id INT NOT NULL,
    actor_name VARCHAR(255) NOT NULL,
    event_type ENUM('kommen','gehen','pause_start','pause_ende') NOT NULL,
    event_time DATETIME NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_time (user_id, event_time),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $pdo->prepare('SELECT BenutzerID, Name, Email FROM Benutzer WHERE BenutzerID = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('Benutzer nicht gefunden.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pw = (string)($_POST['new_password'] ?? '');

    if ($name !== '' && $email !== '') {
        if ($pw !== '') {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE Benutzer SET Name=:n, Email=:e, Passwort=:p WHERE BenutzerID=:id');
            $upd->execute([':n' => $name, ':e' => $email, ':p' => $hash, ':id' => $userId]);
        } else {
            $upd = $pdo->prepare('UPDATE Benutzer SET Name=:n, Email=:e WHERE BenutzerID=:id');
            $upd->execute([':n' => $name, ':e' => $email, ':id' => $userId]);
        }
        $_SESSION['user_name'] = $name;
        $ok = 'Profil gespeichert.';

        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $err = 'Name und E-Mail sind Pflichtfelder.';
    }
}

// Bereich: gebuchte Arbeitszeit (aktueller Monat)
$monthStart = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
$monthEnd = (new DateTimeImmutable('last day of this month 23:59:59'))->format('Y-m-d H:i:s');
$evStmt = $pdo->prepare('SELECT event_type, event_time FROM zeit_manual_events WHERE user_id = :uid AND event_time BETWEEN :from AND :to ORDER BY event_time ASC');
$evStmt->execute([':uid' => $userId, ':from' => $monthStart, ':to' => $monthEnd]);
$events = $evStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSeconds = 0;
$workStart = null;
$pauseStart = null;

foreach ($events as $e) {
    $t = strtotime((string)$e['event_time']);
    if ($t === false) {
        continue;
    }

    switch ((string)$e['event_type']) {
        case 'kommen':
            if ($workStart === null) {
                $workStart = $t;
            }
            break;
        case 'pause_start':
            if ($workStart !== null && $pauseStart === null) {
                $pauseStart = $t;
            }
            break;
        case 'pause_ende':
            if ($workStart !== null && $pauseStart !== null && $t > $pauseStart) {
                $workStart += ($t - $pauseStart); // Pause aus Arbeitszeit rausrechnen
                $pauseStart = null;
            }
            break;
        case 'gehen':
            if ($workStart !== null && $t > $workStart) {
                if ($pauseStart !== null && $t > $pauseStart) {
                    $t = $pauseStart; // offener Pausenstart bis Gehen nicht zählen
                }
                $totalSeconds += max(0, $t - $workStart);
            }
            $workStart = null;
            $pauseStart = null;
            break;
    }
}

$totalHours = floor($totalSeconds / 3600);
$totalMinutes = floor(($totalSeconds % 3600) / 60);

$title = 'Mein Profil';
include __DIR__ . '/../includes/layout.php';
?>
<main class="container mt-4">
    <h1>Mein Profil</h1>

    <?php if (!empty($ok)): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <section class="card p-3 mb-4" style="max-width:700px;">
        <h3>Profildaten</h3>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input class="form-control" type="text" name="name" value="<?= htmlspecialchars((string)$user['Name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">E-Mail</label>
                <input class="form-control" type="email" name="email" value="<?= htmlspecialchars((string)$user['Email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Neues Passwort (optional)</label>
                <input class="form-control" type="password" name="new_password" minlength="6" placeholder="leer lassen = unverändert">
            </div>
            <button class="btn btn-primary" type="submit" name="save_profile" value="1">Speichern</button>
        </form>
    </section>

    <section class="card p-3" style="max-width:900px;">
        <h3>Gebuchte Arbeitszeit (aktueller Monat)</h3>
        <p><strong><?= (int)$totalHours ?>h <?= (int)$totalMinutes ?>min</strong></p>

        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>Zeitpunkt</th><th>Ereignis</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($events) as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$e['event_time']) ?></td>
                        <td><?= htmlspecialchars((string)$e['event_type']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
