<?php
// expects $conversations and optional $conversation
require_once __DIR__ . '/../../../includes/user_check.php';
?>
<?php include __DIR__ . '/../../../public/head.php'; ?>
<link rel="stylesheet" href="/css/messages.css">
<body>
    <?php include __DIR__ . '/../../../public/nav.php'; ?>
    <main>
        <h1>Nachrichten</h1>
        <div style="text-align: right;">
            <button class="btn btn-primary" onclick="openModal('composeModal')">Neue Nachricht</button>
        </div>

        <?php if (!empty($success)): ?>
            <p class="success">Nachricht gesendet.</p>
        <?php endif; ?>

        <div class="inbox-container">
            <div class="conversation-list">
                <ul>
                    <?php foreach ($conversations as $conv): ?>
                        <li data-other-id="<?= htmlspecialchars($conv['other_id']) ?>">
                            <strong><?= htmlspecialchars($conv['other_name']) ?></strong><br>
                            <span><?= htmlspecialchars($conv['subject']) ?></span><br>
                            <span class="preview"><?= htmlspecialchars(mb_strimwidth($conv['body'], 0, 40, '…')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="conversation-panel" id="conversation-panel">
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
        <?php include __DIR__ . '/../../../public/modals/message_compose.php'; ?>
    </main>
    <script src="/js/modal.js"></script>
    <script src="/js/messages.js"></script>
</body>
</html>

