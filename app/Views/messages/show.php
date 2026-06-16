<?php
// expects $messages
?>
<main>
    <h1>Verlauf</h1>
    <?php if (empty($messages)): ?>
        <p>Keine Nachrichten vorhanden.</p>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message">
                <p><strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong> <?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                <p><em><?= htmlspecialchars($msg['created_at']) ?></em></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <p><a href="/messages">Zurück zur Übersicht</a></p>
</main>

