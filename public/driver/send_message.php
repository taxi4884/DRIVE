<?php
require_once '../../includes/bootstrap.php';
require_once '../../includes/driver_helpers.php';
require_once __DIR__ . '/error_handler.php';

try {
    $driverId = requireDriverId();
} catch (RuntimeException $e) {
    driver_log_exception($e);
    header('Location: login.php');
    exit;
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

$formErrors = [];
$generalErrors = [];
$formValues = [
    'recipient_id' => $recipients[0]['recipient_id'] ?? 0,
    'subject' => '',
    'body' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['recipient_id'] = (int)($_POST['recipient_id'] ?? $formValues['recipient_id']);
    $formValues['subject'] = trim($_POST['subject'] ?? '');
    $formValues['body'] = trim($_POST['body'] ?? '');

    $allowed = array_column($recipients, 'recipient_id');
    if (!in_array($formValues['recipient_id'], $allowed, true)) {
        $formErrors['recipient_id'] = 'Ungültiger Empfänger.';
    }

    if ($formValues['subject'] === '') {
        $formErrors['subject'] = 'Bitte einen Betreff eingeben.';
    }

    if ($formValues['body'] === '') {
        $formErrors['body'] = 'Bitte eine Nachricht eingeben.';
    }

    if (empty($formErrors)) {
        try {
            $insert = $pdo->prepare(
                'INSERT INTO messages (sender_id, recipient_id, subject, body)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$driverId, $formValues['recipient_id'], $formValues['subject'], $formValues['body']]);

            header('Location: dashboard.php?message_sent=1');
            exit;
        } catch (PDOException $e) {
            $generalErrors[] = 'Die Nachricht konnte nicht gesendet werden. Bitte versuche es später erneut.';
        }
    }
}

$title = 'Nachricht senden';
$extraCss = ['css/custom.css', 'css/index.css', 'css/form-feedback.css'];
include __DIR__ . '/../../includes/layout.php';
?>
<div class="wrapper">
    <h1>Nachricht senden</h1>
    <?php if (!empty($generalErrors)): ?>
        <div class="form-feedback form-feedback--error">
            <ul>
                <?php foreach ($generalErrors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post">
        <label for="recipient">Empfänger:</label>
        <select name="recipient_id" id="recipient" required>
            <?php foreach ($recipients as $r): ?>
                <option value="<?= $r['recipient_id'] ?>" <?= ($formValues['recipient_id'] === (int) $r['recipient_id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['Name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($formErrors['recipient_id'])): ?>
            <p class="field-error"><?= htmlspecialchars($formErrors['recipient_id']) ?></p>
        <?php endif; ?>
        <label for="subject">Betreff:</label>
        <input type="text" name="subject" id="subject" value="<?= htmlspecialchars($formValues['subject']) ?>" required>
        <?php if (!empty($formErrors['subject'])): ?>
            <p class="field-error"><?= htmlspecialchars($formErrors['subject']) ?></p>
        <?php endif; ?>
        <label for="body">Nachricht:</label>
        <textarea name="body" id="body" required><?= htmlspecialchars($formValues['body']) ?></textarea>
        <?php if (!empty($formErrors['body'])): ?>
            <p class="field-error"><?= htmlspecialchars($formErrors['body']) ?></p>
        <?php endif; ?>
        <button type="submit">Senden</button>
    </form>
</div>
</body>
</html>
