<?php
include '../includes/bootstrap.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$companyId = $_GET['company_id'] ?? $_SESSION['company_id'] ?? null;
$companyId = filter_var($companyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

// Fahrer für Dropdown abrufen
$sql_fahrer_list = "SELECT FahrerID, Vorname, Nachname FROM Fahrer WHERE Status IN ('aktiv', 'Aktiv')";
$fahrerListParams = [];
if ($companyId !== null) {
    $sql_fahrer_list .= " AND company_id = ?";
    $fahrerListParams[] = $companyId;
}
$sql_fahrer_list .= " ORDER BY Nachname, Vorname";
$stmt_fahrer_list = $pdo->prepare($sql_fahrer_list);
$stmt_fahrer_list->execute($fahrerListParams);
$fahrer_list = $stmt_fahrer_list->fetchAll(PDO::FETCH_ASSOC);

$selected_fahrer = $_GET['fahrer'] ?? '';
$selectedFilterClause = $selected_fahrer !== '' ? " AND u.FahrerID = :fahrerID" : "";

$selectedDriverData = null;
if ($selected_fahrer !== '') {
    $stmt_selected_driver = $pdo->prepare("SELECT FahrerID, Vorname, Nachname, Email, Telefonnummer, Strasse, Hausnummer, PLZ, Ort FROM Fahrer WHERE FahrerID = ? LIMIT 1");
    $stmt_selected_driver->execute([(int)$selected_fahrer]);
    $selectedDriverData = $stmt_selected_driver->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Durchschnittlicher Tagesumsatz, Monatsumsatz, Umsatz je Wochentag
$companyClause = $companyId !== null ? " AND f.company_id = :company_id" : "";

$sql_tagesumsatz = "SELECT f.Vorname, f.Nachname,
                         SUM(u.TaxameterUmsatz + u.OhneTaxameter) / COUNT(DISTINCT DATE(u.Datum)) AS umsatz_pro_tag
                  FROM Umsatz u
                  JOIN Fahrer f ON u.FahrerID = f.FahrerID
                  WHERE f.Status IN ('aktiv', 'Aktiv')$companyClause$selectedFilterClause
                  GROUP BY u.FahrerID
                  ORDER BY umsatz_pro_tag DESC";
$stmt_tagesumsatz = $pdo->prepare($sql_tagesumsatz);
if ($companyId !== null) {
    $stmt_tagesumsatz->bindValue(':company_id', $companyId, PDO::PARAM_INT);
}
if ($selected_fahrer !== '') {
    $stmt_tagesumsatz->bindValue(':fahrerID', (int)$selected_fahrer, PDO::PARAM_INT);
}
$stmt_tagesumsatz->execute();
$result_tagesumsatz = $stmt_tagesumsatz->fetchAll(PDO::FETCH_ASSOC);

$best_fahrer = $result_tagesumsatz[0] ?? ['umsatz_pro_tag' => 0];
$schlechtester_fahrer = !empty($result_tagesumsatz) ? end($result_tagesumsatz) : ['umsatz_pro_tag' => 0];
$gesamt_durchschnitt = !empty($result_tagesumsatz)
    ? (array_sum(array_column($result_tagesumsatz, 'umsatz_pro_tag')) / count($result_tagesumsatz))
    : 0;

$sql_monatsumsatz = "SELECT f.Vorname, f.Nachname, DATE_FORMAT(u.Datum, '%Y-%m') AS monat,
                             SUM(u.TaxameterUmsatz + u.OhneTaxameter) AS gesamtumsatz
                      FROM Umsatz u
                      JOIN Fahrer f ON u.FahrerID = f.FahrerID
                      WHERE f.Status IN ('aktiv', 'Aktiv')$companyClause$selectedFilterClause
                      GROUP BY f.FahrerID, monat
                      ORDER BY monat DESC";
$stmt_monatsumsatz = $pdo->prepare($sql_monatsumsatz);
if ($companyId !== null) {
    $stmt_monatsumsatz->bindValue(':company_id', $companyId, PDO::PARAM_INT);
}
if ($selected_fahrer !== '') {
    $stmt_monatsumsatz->bindValue(':fahrerID', (int)$selected_fahrer, PDO::PARAM_INT);
}
$stmt_monatsumsatz->execute();
$result_monatsumsatz = $stmt_monatsumsatz->fetchAll(PDO::FETCH_ASSOC);

$sql_wochentagsumsatz = "SELECT f.Vorname, f.Nachname, DAYNAME(u.Datum) AS wochentag,
                                 AVG(u.TaxameterUmsatz + u.OhneTaxameter) AS durchschnitt_umsatz
                          FROM Umsatz u
                          JOIN Fahrer f ON u.FahrerID = f.FahrerID
                          WHERE f.Status IN ('aktiv', 'Aktiv')$companyClause$selectedFilterClause
                          GROUP BY f.FahrerID, wochentag
                          ORDER BY wochentag";
$stmt_wochentagsumsatz = $pdo->prepare($sql_wochentagsumsatz);
if ($companyId !== null) {
    $stmt_wochentagsumsatz->bindValue(':company_id', $companyId, PDO::PARAM_INT);
}
if ($selected_fahrer !== '') {
    $stmt_wochentagsumsatz->bindValue(':fahrerID', (int)$selected_fahrer, PDO::PARAM_INT);
}
$stmt_wochentagsumsatz->execute();
$result_wochentagsumsatz = $stmt_wochentagsumsatz->fetchAll(PDO::FETCH_ASSOC);

$title = 'Fahrer-Vergleich';
include __DIR__ . '/../includes/layout.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="js/modal.js"></script>

<main>
    <h2>Vergleich Fahrer-Statistik</h2>
    <form method="get">
        <?php if ($companyId !== null): ?>
            <input type="hidden" name="company_id" value="<?= (int)$companyId ?>">
        <?php endif; ?>
        <label for="fahrer">Fahrer auswählen:</label>
        <select name="fahrer" id="fahrer" onchange="this.form.submit()">
            <option value="">-- Wähle einen Fahrer --</option>
            <?php foreach ($fahrer_list as $fahrer): ?>
                <option value="<?= (int)$fahrer['FahrerID'] ?>" <?= ((string)$fahrer['FahrerID'] === (string)$selected_fahrer) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fahrer['Nachname'] . ', ' . $fahrer['Vorname']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selected_fahrer !== ''): ?>
        <?php if ($selectedDriverData): ?>
            <div class="alert alert-info mt-3">
                <strong>Ausgewählter Fahrer:</strong> <?= htmlspecialchars(($selectedDriverData['Vorname'] ?? '') . ' ' . ($selectedDriverData['Nachname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <br><small>ID: <?= (int)$selectedDriverData['FahrerID'] ?><?php if (!empty($selectedDriverData['Email'])): ?> · <?= htmlspecialchars((string)$selectedDriverData['Email'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></small>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-3">Der ausgewählte Fahrer wurde nicht gefunden.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($selected_fahrer !== '' && empty($result_tagesumsatz)): ?>
        <div class="alert alert-warning">Für den ausgewählten Fahrer sind aktuell keine Umsatzdaten vorhanden.</div>
    <?php endif; ?>

    <h3>Durchschnittlicher Tagesumsatz</h3>
    <canvas id="tagesumsatzChart"></canvas>
    <script>
        const ctx1 = document.getElementById('tagesumsatzChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Durchschnitt', 'Bester Fahrer', 'Schlechtester Fahrer'],
                datasets: [{
                    label: 'Umsatz pro Tag (€)',
                    data: <?= json_encode([(float)$gesamt_durchschnitt, (float)$best_fahrer['umsatz_pro_tag'], (float)$schlechtester_fahrer['umsatz_pro_tag']]) ?>,
                    backgroundColor: ['gray', 'green', 'red']
                }]
            }
        });
    </script>

    <h3>Monatsumsatz</h3>
    <canvas id="monatsumsatzChart"></canvas>
    <script>
        const ctx2 = document.getElementById('monatsumsatzChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_values(array_column($result_monatsumsatz, 'monat'))) ?>,
                datasets: [{
                    label: 'Monatsumsatz (€)',
                    data: <?= json_encode(array_map('floatval', array_column($result_monatsumsatz, 'gesamtumsatz'))) ?>,
                    borderColor: 'blue',
                    fill: false
                }]
            }
        });
    </script>

    <h3>Umsatz je Wochentag</h3>
    <canvas id="wochentagsumsatzChart"></canvas>
    <script>
        const ctx3 = document.getElementById('wochentagsumsatzChart').getContext('2d');
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_values(array_column($result_wochentagsumsatz, 'wochentag'))) ?>,
                datasets: [{
                    label: 'Durchschnittlicher Umsatz (€)',
                    data: <?= json_encode(array_map('floatval', array_column($result_wochentagsumsatz, 'durchschnitt_umsatz'))) ?>,
                    backgroundColor: 'orange'
                }]
            }
        });
    </script>
</main>

</body>
</html>
