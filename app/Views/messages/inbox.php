<?php
// expects $conversations and optional $conversation
?>
<main>
    <h1>Nachrichten</h1>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="margin-bottom: 1rem;">
        <div class="notification-permission">
            <button type="button" class="btn btn-secondary" id="enable-notifications" style="display: none;">
                Desktop-Benachrichtigungen aktivieren
            </button>
        </div>
        <div style="text-align: right;">
            <button class="btn btn-primary" onclick="openModal('composeModal')">Neue Nachricht</button>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <p class="success">Nachricht gesendet.</p>
    <?php endif; ?>

    <div class="inbox-container">
        <div class="conversation-list">
            <?php foreach ($conversations as $conv): ?>
                <?php $unreadCount = (int) ($conv['unread_count'] ?? 0); ?>
                <div
                    class="card conversation-item<?= $unreadCount > 0 ? ' has-unread' : '' ?>"
                    data-other-id="<?= htmlspecialchars($conv['other_id']) ?>"
                    data-subject="<?= htmlspecialchars($conv['subject']) ?>"
                    data-unread-count="<?= $unreadCount ?>"
                >
                    <div class="conversation-item-header">
                        <strong><?= htmlspecialchars($conv['other_name']) ?></strong>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="conversation-subject"><?= htmlspecialchars($conv['subject']) ?></span>
                    <span class="preview"><?= htmlspecialchars(mb_strimwidth($conv['body'], 0, 40, '…')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="conversation-panel" id="conversation-panel">
            <div id="conversation-content">
                <?php if (!empty($conversation)): ?>
                    <?php foreach ($conversation as $msg): ?>
                        <?php $isSent = isset($currentUserId) && (int) $msg['sender_id'] === (int) $currentUserId; ?>
                        <div class="message <?= $isSent ? 'sent' : 'received' ?>">
                            <p class="message-header"><strong><?= htmlspecialchars($msg['sender_name']) ?></strong> am <?= htmlspecialchars($msg['created_at']) ?></p>
                            <?php if (!empty($msg['subject'])): ?>
                                <p class="message-subject"><span>Betreff:</span> <?= htmlspecialchars($msg['subject']) ?></p>
                            <?php endif; ?>
                            <p class="message-body"><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                            <?php if (!empty($msg['read_at'])): ?>
                                <p class="message-meta">Gelesen am <?= htmlspecialchars($msg['read_at']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form id="chat-form" method="post" action="/postfach.php?action=store">
                <input type="hidden" name="recipient_id" id="chat-recipient-id">
                <input type="hidden" name="subject" id="chat-subject">
                <textarea name="body" id="chat-body" rows="3" placeholder="Nachricht" required></textarea>
                <button type="submit">Senden</button>
            </form>
        </div>
    </div>
    <?php include __DIR__ . '/../../../public/modals/message_compose.php'; ?>
</main>
<script src="/js/modal.js"></script>
<script src="/js/messages.js"></script>

