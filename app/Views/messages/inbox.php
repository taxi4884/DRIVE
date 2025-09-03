<?php
// expects $conversations and optional $conversation
?>
<?php include __DIR__ . '/../../../public/head.php'; ?>
<link rel="stylesheet" href="/css/messages.css">
<body>
    <?php include __DIR__ . '/../../../public/nav.php'; ?>
    <main>
        <h1>Nachrichten</h1>

        <?php if (!empty($success)): ?>
            <p class="success">Nachricht gesendet.</p>
        <?php endif; ?>

        <div class="inbox-container">
            <div class="conversation-list">
                <ul>
                    <?php foreach ($conversations as $conv): ?>
                        <li data-other-id="<?= htmlspecialchars($conv['other_id']) ?>">
                            <strong><?= htmlspecialchars($conv['other_name']) ?></strong><br>
                            <span><?= htmlspecialchars($conv['subject']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="conversation-panel" id="conversation-panel">
                <a href="/postfach.php?action=compose" class="new-message-btn">Neue Nachricht</a>
                <div id="conversation-content">
                    <?php if (!empty($conversation)): ?>
                        <?php foreach ($conversation as $msg): ?>
                            <div class="message">
                                <p><strong><?= htmlspecialchars($msg['sender_name']) ?></strong> am <?= htmlspecialchars($msg['created_at']) ?></p>
                                <p><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="/js/messages.js"></script>
</body>
</html>

