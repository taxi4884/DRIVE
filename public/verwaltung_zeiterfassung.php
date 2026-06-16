<?php
require_once '../includes/bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

// Neue Rechtefreigabe-Spalte sicherstellen
try {
    $pdo->exec("ALTER TABLE Benutzer ADD COLUMN ZeitpruefungFreigabe TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {
}

$role = strtolower((string)($_SESSION['rolle'] ?? ''));
$secondary = $_SESSION['sekundarRolle'] ?? [];
if (!is_array($secondary)) {
    $secondary = array_filter(array_map('trim', explode(',', (string)$secondary)));
}
$secondaryLower = array_map('strtolower', $secondary);

$permStmt = $pdo->prepare('SELECT Name, ZeitpruefungFreigabe FROM Benutzer WHERE BenutzerID = :id');
$permStmt->execute([':id' => $userId]);
$me = $permStmt->fetch(PDO::FETCH_ASSOC) ?: ['Name' => 'Unbekannt', 'ZeitpruefungFreigabe' => 0];

if ((int)($me['ZeitpruefungFreigabe'] ?? 0) !== 1) {
    http_response_code(403);
    die('Kein Zugriff auf Zeiterfassung-Prüfung.');
}

$canManageAll = in_array($role, ['admin', 'mitarbeiter'], true)
    || in_array('verwaltung', $secondaryLower, true)
    || (int)($me['ZeitpruefungFreigabe'] ?? 0) === 1;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $targetUserId = (int)($_POST['target_user_id'] ?? $userId);
    $eventType = (string)($_POST['event_type'] ?? '');
    $eventTime = (string)($_POST['event_time'] ?? '');
    $note = trim((string)($_POST['note'] ?? ''));

    $validTypes = ['kommen','gehen','pause_start','pause_ende'];
    if (!in_array($eventType, $validTypes, true)) {
        $err = 'Ungültiger Ereignistyp.';
    } else {
        if (!$canManageAll) {
            $targetUserId = $userId;
        }

        $uStmt = $pdo->prepare('SELECT BenutzerID, Name FROM Benutzer WHERE BenutzerID = :id LIMIT 1');
        $uStmt->execute([':id' => $targetUserId]);
        $target = $uStmt->fetch(PDO::FETCH_ASSOC);

        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $eventTime) ?: DateTime::createFromFormat('Y-m-d H:i:s', $eventTime);

        if (!$target) {
            $err = 'Zielbenutzer nicht gefunden.';
        } elseif (!$dt) {
            $err = 'Ungültiges Datum/Uhrzeit.';
        } else {
            $ins = $pdo->prepare('INSERT INTO zeit_manual_events (user_id, user_name, actor_user_id, actor_name, event_type, event_time, note) VALUES (:uid,:uname,:aid,:aname,:etype,:etime,:note)');
            $ins->execute([
                ':uid' => (int)$target['BenutzerID'],
                ':uname' => (string)$target['Name'],
                ':aid' => $userId,
                ':aname' => (string)($me['Name'] ?? 'System'),
                ':etype' => $eventType,
                ':etime' => $dt->format('Y-m-d H:i:s'),
                ':note' => $note !== '' ? $note : null,
            ]);
            $ok = 'Nachbuchung gespeichert.';
        }
    }
}

$users = [];
if ($canManageAll) {
    $users = $pdo->query('SELECT BenutzerID, Name FROM Benutzer ORDER BY Name ASC')->fetchAll(PDO::FETCH_ASSOC);
}

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));

$params = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59'];
$sql = 'SELECT * FROM zeit_manual_events WHERE event_time BETWEEN :from AND :to';
if (!$canManageAll) {
    $sql .= ' AND user_id = :uid';
    $params[':uid'] = $userId;
}
$sql .= ' ORDER BY event_time DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($params);
$manualEvents = $st->fetchAll(PDO::FETCH_ASSOC);

$limit = 200;
$zeitSql = 'SELECT id, programm_id, programm_name, message_type, message_id, source_received_at, created_at FROM zeit_entries ORDER BY id DESC LIMIT :lim';
$zeit = $pdo->prepare($zeitSql);
$zeit->bindValue(':lim', $limit, PDO::PARAM_INT);
$zeit->execute();
$zeitEntries = $zeit->fetchAll(PDO::FETCH_ASSOC);

$title = 'Zeiterfassung prüfen';
include __DIR__ . '/../includes/layout.php';
?>
<main class="container mt-4">
    <h1>Zeiterfassung prüfen</h1>
    <p class="text-muted">Manuelle Nachbuchung von Kommen/Gehen/Pausen und Kontrolle der eingehenden Zeitmeldungen.</p>

    <?php if (!empty($ok)): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <section class="card p-3 mb-4">
        <h3>Manuell nachbuchen</h3>
        <form method="post" class="row g-3">
            <?php if ($canManageAll): ?>
            <div class="col-md-4">
                <label class="form-label">Mitarbeiter</label>
                <select class="form-select" name="target_user_id" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['BenutzerID'] ?>"><?= htmlspecialchars((string)$u['Name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Ereignis</label>
                <select class="form-select" name="event_type" required>
                    <option value="kommen">Kommen</option>
                    <option value="gehen">Gehen</option>
                    <option value="pause_start">Pause Start</option>
                    <option value="pause_ende">Pause Ende</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Zeitpunkt</label>
                <input class="form-control" type="datetime-local" name="event_time" value="<?= date('Y-m-d\\TH:i') ?>" required>
            </div>
            <div class="col-md-10">
                <label class="form-label">Notiz (optional)</label>
                <input class="form-control" type="text" name="note" maxlength="255">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" name="add_event" value="1">Speichern</button>
            </div>
        </form>
    </section>

    <section class="card p-3 mb-4">
        <h3>Manuelle Buchungen</h3>
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= htmlspecialchars($from) ?>"></div>
            <div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= htmlspecialchars($to) ?>"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary" type="submit">Filtern</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>Zeit</th><th>Mitarbeiter</th><th>Typ</th><th>Notiz</th><th>Gebucht von</th></tr></thead>
                <tbody>
                <?php foreach ($manualEvents as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$e['event_time']) ?></td>
                        <td><?= htmlspecialchars((string)$e['user_name']) ?></td>
                        <td><?= htmlspecialchars((string)$e['event_type']) ?></td>
                        <td><?= htmlspecialchars((string)($e['note'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)$e['actor_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card p-3">
        <h3>Eingehende Zeitmeldungen (Bridge)</h3>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>ID</th><th>Programm</th><th>Typ</th><th>Message</th><th>Quelle Zeit</th><th>Empfangen</th></tr></thead>
                <tbody>
                <?php foreach ($zeitEntries as $z): ?>
                    <tr>
                        <td><?= (int)$z['id'] ?></td>
                        <td><?= htmlspecialchars((string)$z['programm_name']) ?> <small class="text-muted"><?= htmlspecialchars((string)$z['programm_id']) ?></small></td>
                        <td><?= htmlspecialchars((string)$z['message_type']) ?></td>
                        <td><?= htmlspecialchars((string)$z['message_id']) ?></td>
                        <td><?= htmlspecialchars((string)$z['source_received_at']) ?></td>
                        <td><?= htmlspecialchars((string)$z['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
