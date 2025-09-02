<?php
require_once '../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    $messageId = (int)$_POST['mark_as_read'];
    $update = $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE id = ? AND recipient_id = ?');
    $update->execute([$messageId, $userId]);
    header('Location: inbox.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT m.id, m.subject, m.body, m.created_at, b.Name AS sender_name
    FROM messages m
    JOIN Benutzer b ON m.sender_id = b.BenutzerID
    WHERE m.recipient_id = ? AND m.read_at IS NULL
    ORDER BY m.created_at DESC
');
$stmt->execute([$userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Inbox</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <?php include '../nav.php'; ?>
    <main>
        <h1>Ungelesene Nachrichten</h1>
        <?php if (empty($messages)): ?>
            <p>Keine ungelesenen Nachrichten.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message">
                    <h2><?= htmlspecialchars($msg['subject']) ?></h2>
                    <p><em>Von <?= htmlspecialchars($msg['sender_name']) ?> am <?= htmlspecialchars($msg['created_at']) ?></em></p>
                    <p><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="mark_as_read" value="<?= $msg['id'] ?>">
                        <button type="submit">Als gelesen markieren</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
