<?php
// Formular zum Verfassen einer neuen Nachricht
?>
<main>
    <h1>Neue Nachricht</h1>
    <form method="post" action="/postfach.php?action=store" class="message-form">
        <div class="form-group">
            <label for="recipient_id">Empfänger</label>
            <select id="recipient_id" name="recipient_id" required>
                <?php foreach ($recipients as $r): ?>
                    <option value="<?= $r['BenutzerID'] ?>"><?= htmlspecialchars($r['Name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="subject">Betreff</label>
            <input type="text" id="subject" name="subject" required>
        </div>
        <div class="form-group">
            <label for="body">Nachricht</label>
            <textarea id="body" name="body" rows="5" required></textarea>
        </div>
        <button type="submit">Senden</button>
    </form>
</main>

