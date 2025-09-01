<?php
require_once '../includes/head.php';
require_once 'modals/process_driver.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Aktionen: wichtig, sichtbar, löschen
if (isset($_GET['aktion']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    switch ($_GET['aktion']) {
        case 'toggle_wichtig':
            $pdo->query("UPDATE fahrer_mitteilungen SET wichtig = NOT wichtig WHERE id = $id");
            break;
        case 'toggle_sichtbar':
            $pdo->query("UPDATE fahrer_mitteilungen SET sichtbar = NOT sichtbar WHERE id = $id");
            break;
        case 'delete':
            $pdo->query("DELETE FROM fahrer_mitteilungen WHERE id = $id");
            break;
        case 'edit':
            if (!empty($_POST['nachricht']) && !empty($_POST['gueltig_bis'])) {
                $stmt = $pdo->prepare("UPDATE fahrer_mitteilungen SET nachricht = ?, gueltig_bis = ? WHERE id = ?");
                $stmt->execute([trim($_POST['nachricht']), $_POST['gueltig_bis'], $id]);
            }
            break;
    }
    header("Location: fahrer.php");
    exit();
}

// Fahrer abrufen
$stmt = $pdo->query("SELECT FahrerID, Vorname, Nachname, Telefonnummer, FuehrerscheinGueltigkeit, PScheinGueltigkeit FROM Fahrer");
$fahrer = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktuelle Mitteilung abrufen
$stmtMsg = $pdo->prepare("SELECT * FROM fahrer_mitteilungen WHERE gueltig_bis >= NOW() ORDER BY erstellt_am DESC LIMIT 1");
$stmtMsg->execute();
$mitteilung = $stmtMsg->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrerübersicht | DRIVE</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/modal.js"></script>
</head>
<body>
    <?php include 'nav.php'; ?>
    <main>
        <h1>Fahrerübersicht</h1>

        <?php if (!empty($mitteilung)): ?>
			<div class="hinweis-box" style="background: <?= $mitteilung['wichtig'] ? '#ffe4e4' : '#fffae6' ?>; border: 1px solid <?= $mitteilung['wichtig'] ? '#cc0000' : '#e6c300' ?>; padding: 10px; margin-bottom: 20px;">
				<strong>📢 Mitteilung an alle Fahrer:</strong><br>
				<?= nl2br(htmlspecialchars($mitteilung['nachricht'])) ?><br><br>
				<small>Gültig bis: <?= date('d.m.Y', strtotime($mitteilung['gueltig_bis'])) ?> | erstellt von <?= htmlspecialchars($mitteilung['erstellt_von']) ?></small>

				<div style="margin-top: 10px;">
					<a class="btn-sm" href="?aktion=toggle_wichtig&id=<?= $mitteilung['id'] ?>">Wichtig <?= $mitteilung['wichtig'] ? '🔴' : '⚪' ?></a>
					<a class="btn-sm" href="?aktion=toggle_sichtbar&id=<?= $mitteilung['id'] ?>">Ausblenden</a>
					<a class="btn-sm" href="?aktion=delete&id=<?= $mitteilung['id'] ?>" onclick="return confirm('Wirklich löschen?')">🗑️ Löschen</a>
					<button class="btn-sm" onclick="document.getElementById('editForm').style.display='block'">✏️ Bearbeiten</button>
				</div>

				<form id="editForm" action="?aktion=edit&id=<?= $mitteilung['id'] ?>" method="POST" style="display: none; margin-top: 10px;">
					<textarea name="nachricht" rows="4" required><?= htmlspecialchars($mitteilung['nachricht']) ?></textarea><br>
					<label>Gültig bis:</label>
					<input type="date" name="gueltig_bis" value="<?= date('Y-m-d', strtotime($mitteilung['gueltig_bis'])) ?>" required>
					<button type="submit" class="btn-sm">Speichern</button>
				</form>
			</div>
		<?php endif; ?>

        <div class="button-group">
            <button class="btn" onclick="openModal('driverModal')">Neuen Fahrer hinzufügen</button>
            <button class="btn" onclick="openModal('messageModal')">Mitteilung an Fahrer senden</button>
        </div>

        <table class="styled-table">
            <thead>
                <tr>
                    <th>Vorname</th>
                    <th>Nachname</th>
                    <th>Telefonnummer</th>
                    <th>Führerschein gültig bis</th>
                    <th>P-Schein gültig bis</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($fahrer)): ?>
                    <?php foreach ($fahrer as $driver): ?>
                        <tr>
                            <td><?= htmlspecialchars($driver['Vorname']) ?></td>
                            <td><?= htmlspecialchars($driver['Nachname']) ?></td>
                            <td><?= htmlspecialchars($driver['Telefonnummer']) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y', strtotime($driver['FuehrerscheinGueltigkeit']))) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y', strtotime($driver['PScheinGueltigkeit']))) ?></td>
                            <td>
                                <a href="fahrer_bearbeiten.php?id=<?= $driver['FahrerID'] ?>" class="btn-sm">Bearbeiten</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Keine Fahrer gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <?php include 'modals/add_driver_modal.php'; ?>

    <!-- Modal für Mitteilung -->
    <div class="modal" id="messageModal" style="display: none;">
        <div class="modal-content">
            <h2>Mitteilung an alle Fahrer</h2>
            <form action="fahrer_mitteilung_senden.php" method="POST">
                <textarea name="nachricht" rows="5" required placeholder="Nachricht an alle Fahrer"></textarea><br>
                <label for="gueltig_bis">Gültig bis:</label>
                <input type="date" name="gueltig_bis" required><br><br>
                <input type="hidden" name="erstellt_von" value="<?= htmlspecialchars($_SESSION['nutzername'] ?? 'Chrissi') ?>">
                <button type="submit" class="btn">Senden</button>
                <button type="button" class="btn" onclick="closeModal('messageModal')">Abbrechen</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelector('.burger-menu')?.addEventListener('click', () => {
            document.querySelector('.nav-links')?.classList.toggle('active');
        });
    </script>
</body>
</html>
