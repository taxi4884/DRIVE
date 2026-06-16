<?php
// public/umsatz_verlauf.php
require_once '../includes/bootstrap.php';

$umsatzid = $_GET['umsatzid'] ?? null;
$companyId = $_SESSION['company_id'] ?? null;

if (!$umsatzid) {
    die('Keine Umsatz-ID übergeben.');
}

$aenderungenSql = "
    SELECT Benutzer, Zeitpunkt, Feldname, AlterWert, NeuerWert
    FROM Umsatz_Aenderungen
    WHERE UmsatzID = ?
    ORDER BY Zeitpunkt DESC
";
$aenderungenParams = [$umsatzid];
if ($companyId !== null) {
    $aenderungenSql = "
        SELECT ua.Benutzer, ua.Zeitpunkt, ua.Feldname, ua.AlterWert, ua.NeuerWert
        FROM Umsatz_Aenderungen ua
        JOIN Umsatz u ON ua.UmsatzID = u.UmsatzID
        JOIN Fahrer f ON u.FahrerID = f.FahrerID
        WHERE ua.UmsatzID = ? AND f.company_id = ?
        ORDER BY ua.Zeitpunkt DESC
    ";
    $aenderungenParams[] = $companyId;
}
$stmt = $pdo->prepare($aenderungenSql);
$stmt->execute($aenderungenParams);
$aenderungen = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($aenderungen)) {
    echo "<p>Keine Änderungen gefunden.</p>";
    return;
}
?>

<table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr>
            <th>🧑‍💼 Benutzer</th>
            <th>📅 Zeitpunkt</th>
            <th>📌 Feld</th>
            <th>⬅️ Alt</th>
            <th>➡️ Neu</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($aenderungen as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['Benutzer']) ?></td>
                <td><?= date('d.m.y H:i', strtotime($log['Zeitpunkt'])) ?></td>
                <td><?= htmlspecialchars($log['Feldname']) ?></td>
                <td><?= htmlspecialchars($log['AlterWert']) ?></td>
                <td><?= htmlspecialchars($log['NeuerWert']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
