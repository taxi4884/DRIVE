<?php
// expects $conversations
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Nachrichten</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php include __DIR__ . '/../../../public/nav.php'; ?>
    <main>
        <h1>Konversationen</h1>
        <?php if (empty($conversations)): ?>
            <p>Keine Nachrichten.</p>
        <?php else: ?>
            <ul>
            <?php foreach ($conversations as $conv): ?>
                <li>
                    <a href="/messages/<?= $conv['other_id'] ?>">
                        <?= htmlspecialchars($conv['other_name']) ?>
                    </a>
                    <p><em><?= htmlspecialchars($conv['created_at']) ?></em></p>
                    <p><?= htmlspecialchars($conv['subject']) ?></p>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
</body>
</html>
