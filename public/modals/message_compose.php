<form action="/postfach.php?action=store" method="post">
    <label for="recipient_id">Empfänger</label>
    <select id="recipient_id" name="recipient_id" required>
        <?php foreach ($recipients as $r): ?>
            <option value="<?= htmlspecialchars($r['BenutzerID']) ?>"><?= htmlspecialchars($r['Name']) ?></option>
        <?php endforeach; ?>
    </select>
    <br>
    <label for="subject">Betreff</label>
    <input type="text" id="subject" name="subject" required>
    <br>
    <label for="body">Nachricht</label>
    <textarea id="body" name="body" required></textarea>
    <br>
    <button type="submit">Senden</button>
</form>
