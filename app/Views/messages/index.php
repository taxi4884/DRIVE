<?php
use App\Models\Message;

// $conversations expected from controller

$selectedUserId = isset($_GET['user']) ? (int) $_GET['user'] : null;
$messages = [];
if ($selectedUserId && isset($_SESSION['user_id'])) {
    $messages = Message::getMessagesBetween((int) $_SESSION['user_id'], $selectedUserId);
}
?>
<main class="messages-container">
        <aside class="conversations">
            <h2>Gesprächspartner</h2>
            <?php if (empty($conversations)): ?>
                <p>Keine Nachrichten.</p>
            <?php else: ?>
                <ul>
                <?php foreach ($conversations as $conv): ?>
                    <li>
                        <a href="/messages?user=<?= $conv['other_id'] ?>">
                            <?= htmlspecialchars($conv['other_name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>

        <section class="conversation">
            <?php if ($selectedUserId && !empty($messages)): ?>
                <h2><?= htmlspecialchars($messages[0]['subject']) ?></h2>
                <div class="messages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message">
                            <p><strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong> <?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                            <p class="timestamp"><em><?= htmlspecialchars($msg['created_at']) ?></em></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($selectedUserId): ?>
                <p>Keine Nachrichten vorhanden.</p>
            <?php else: ?>
                <p>Wähle einen Gesprächspartner aus.</p>
            <?php endif; ?>
        </section>
    </main>

