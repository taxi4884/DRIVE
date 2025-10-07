<?php
require_once '../../includes/bootstrap.php';
require_once '../../includes/driver_helpers.php';

try {
    $driverId = requireDriverId();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// Permitted recipients for this driver
$permStmt = $pdo->prepare(
    'SELECT mp.recipient_id, b.Name
     FROM message_permissions mp
     JOIN Benutzer b ON mp.recipient_id = b.BenutzerID
     WHERE mp.driver_id = ?
     ORDER BY b.Name'
);
$permStmt->execute([$driverId]);
$recipients = $permStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientId = (int)($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    $allowed = array_column($recipients, 'recipient_id');
    if (!in_array($recipientId, $allowed, true)) {
        die('Ungültiger Empfänger.');
    }

    if ($subject === '' || $body === '') {
        die('Betreff und Nachricht dürfen nicht leer sein.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO messages (sender_id, recipient_id, subject, body)
         VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$driverId, $recipientId, $subject, $body]);

    header('Location: dashboard.php?message_sent=1');
    exit;
}

$title = 'Nachricht senden';
$extraCss = ['css/custom.css', 'css/index.css'];
include __DIR__ . '/../../includes/layout.php';
?>
<div class="wrapper">
    <h1>Nachricht senden</h1>
    <form method="post">
        <label for="recipient">Empfänger:</label>
        <select name="recipient_id" id="recipient" required>
            <?php foreach ($recipients as $r): ?>
                <option value="<?= $r['recipient_id'] ?>"><?= htmlspecialchars($r['Name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="subject">Betreff:</label>
        <input type="text" name="subject" id="subject" required>
        <label for="body">Nachricht:</label>
        <textarea name="body" id="body" required></textarea>
        <button type="submit">Senden</button>
    </form>
</div>
</body>
</html>
