<?php
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$stmt = $pdo->query("SELECT FahrerID, Vorname, Nachname, Telefonnummer, FuehrerscheinGueltigkeit, PScheinGueltigkeit FROM Fahrer WHERE Status IN ('inaktiv', 'Inaktiv') ORDER BY Nachname, Vorname");
$fahrer = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Inaktive Fahrer';
include __DIR__ . '/../includes/layout.php';
?>
    <main>
        <h1>Inaktive Fahrer</h1>
        <div class="button-group">
            <a class="btn" href="fahrer.php">Zurück zur Fahrerübersicht</a>
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
                            <td><?= $driver['FuehrerscheinGueltigkeit'] ? htmlspecialchars(date('d.m.y', strtotime($driver['FuehrerscheinGueltigkeit']))) : '-' ?></td>
                            <td><?= $driver['PScheinGueltigkeit'] ? htmlspecialchars(date('d.m.y', strtotime($driver['PScheinGueltigkeit']))) : '-' ?></td>
                            <td>
                                <a href="fahrer_bearbeiten.php?id=<?= $driver['FahrerID'] ?>" class="btn-sm">Bearbeiten</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Keine inaktiven Fahrer gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
