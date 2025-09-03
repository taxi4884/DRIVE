<?php
// Formular zum Verfassen einer neuen Nachricht
?>
<?php include __DIR__ . '/../../../public/head.php'; ?>
<body>
    <?php include __DIR__ . '/../../../public/nav.php'; ?>
    <main>
        <h1>Neue Nachricht</h1>
        <form method="post" action="/postfach.php?action=store">
            <div>
                <label for="recipient_id">Empfänger</label>
                <select id="recipient_id" name="recipient_id" required>
                    <?php foreach ($recipients as $r): ?>
                        <option value="<?= $r['BenutzerID'] ?>"><?= htmlspecialchars($r['Name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="subject">Betreff</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div>
                <label for="body">Nachricht</label>
                <textarea id="body" name="body" required></textarea>
            </div>
            <button type="submit">Senden</button>
        </form>
    </main>
</body>
</html>

