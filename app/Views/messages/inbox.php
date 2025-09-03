<?php
// expects $messages
?>
<?php include __DIR__ . '/../../../public/head.php'; ?>
<link rel="stylesheet" href="/css/messages.css">
<body>
    <?php include __DIR__ . '/../../../public/nav.php'; ?>
    <main>
        <h1>Ungelesene Nachrichten</h1>

        <p><a href="/postfach.php?action=compose">Neue Nachricht</a></p>

        <?php if (!empty($success)): ?>
            <p class="success">Nachricht gesendet.</p>
        <?php endif; ?>
        <?php if (empty($messages)): ?>
            <p>Keine ungelesenen Nachrichten.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message">
                    <h2><?= htmlspecialchars($msg['subject']) ?></h2>
                    <p><em>Von <?= htmlspecialchars($msg['sender_name']) ?> am <?= htmlspecialchars($msg['created_at']) ?></em></p>
                    <p><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                    <form method="post" action="/messages/mark-as-read">
                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                        <button type="submit">Als gelesen markieren</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
