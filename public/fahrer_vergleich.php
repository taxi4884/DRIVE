<?php
include '../includes/bootstrap.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fahrer für Dropdown abrufen
$sql_fahrer_list = "SELECT FahrerID, Vorname, Nachname FROM Fahrer WHERE Aktiv = 1 ORDER BY Nachname, Vorname";
$stmt_fahrer_list = $pdo->prepare($sql_fahrer_list);
$stmt_fahrer_list->execute();
$fahrer_list = $stmt_fahrer_list->fetchAll(PDO::FETCH_ASSOC);

$selected_fahrer = $_GET['fahrer'] ?? '';
$where_clause = $selected_fahrer ? " WHERE u.FahrerID = :fahrerID" : "";

// Durchschnittlicher Tagesumsatz, Monatsumsatz, Umsatz je Wochentag
$sql_tagesumsatz = "SELECT f.Vorname, f.Nachname,
                         SUM(u.TaxameterUmsatz + u.OhneTaxameter) / COUNT(DISTINCT DATE(u.Datum)) AS umsatz_pro_tag
                  FROM Umsatz u
                  JOIN Fahrer f ON u.FahrerID = f.FahrerID
                  WHERE f.Aktiv = 1
                  GROUP BY u.FahrerID
                  ORDER BY umsatz_pro_tag DESC";
$stmt_tagesumsatz = $pdo->prepare($sql_tagesumsatz);
$stmt_tagesumsatz->execute();
$result_tagesumsatz = $stmt_tagesumsatz->fetchAll(PDO::FETCH_ASSOC);

$best_fahrer = $result_tagesumsatz[0];
$schlechtester_fahrer = end($result_tagesumsatz);
$gesamt_durchschnitt = array_sum(array_column($result_tagesumsatz, 'umsatz_pro_tag')) / count($result_tagesumsatz);

$sql_monatsumsatz = "SELECT f.Vorname, f.Nachname, DATE_FORMAT(u.Datum, '%Y-%m') AS monat,
                             SUM(u.TaxameterUmsatz + u.OhneTaxameter) AS gesamtumsatz
                      FROM Umsatz u
                      JOIN Fahrer f ON u.FahrerID = f.FahrerID
                      WHERE f.Aktiv = 1
                      GROUP BY f.FahrerID, monat
                      ORDER BY monat DESC";
$stmt_monatsumsatz = $pdo->prepare($sql_monatsumsatz);
$stmt_monatsumsatz->execute();
$result_monatsumsatz = $stmt_monatsumsatz->fetchAll(PDO::FETCH_ASSOC);

$sql_wochentagsumsatz = "SELECT f.Vorname, f.Nachname, DAYNAME(u.Datum) AS wochentag,
                                 AVG(u.TaxameterUmsatz + u.OhneTaxameter) AS durchschnitt_umsatz
                          FROM Umsatz u
                          JOIN Fahrer f ON u.FahrerID = f.FahrerID
                          WHERE f.Aktiv = 1
                          GROUP BY f.FahrerID, wochentag
                          ORDER BY wochentag";
$stmt_wochentagsumsatz = $pdo->prepare($sql_wochentagsumsatz);
$stmt_wochentagsumsatz->execute();
$result_wochentagsumsatz = $stmt_wochentagsumsatz->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$title = 'Fahrer-Vergleich';
include __DIR__ . '/../includes/layout.php';
?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/modal.js"></script>

  		<main>
    <h2>Vergleich Fahrer-Statistik</h2>
    <form method="get">
        <label for="fahrer">Fahrer auswählen:</label>
        <select name="fahrer" id="fahrer" onchange="this.form.submit()">
            <option value="">-- Wähle einen Fahrer --</option>
            <?php foreach ($fahrer_list as $fahrer): ?>
                <option value="<?= $fahrer['FahrerID'] ?>" <?= ($fahrer['FahrerID'] == $selected_fahrer) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fahrer['Nachname'] . ', ' . $fahrer['Vorname']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <h3>Durchschnittlicher Tagesumsatz</h3>
    <canvas id="tagesumsatzChart"></canvas>
    <script>
        var ctx = document.getElementById('tagesumsatzChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Durchschnitt', 'Bester Fahrer', 'Schlechtester Fahrer'],
                datasets: [{
                    label: 'Umsatz pro Tag (€)',
                    data: [<?= number_format($gesamt_durchschnitt, 2) ?>, <?= number_format($best_fahrer['umsatz_pro_tag'], 2) ?>, <?= number_format($schlechtester_fahrer['umsatz_pro_tag'], 2) ?>],
                    backgroundColor: ['gray', 'green', 'red']
                }]
            }
        });
    </script>

    <h3>Monatsumsatz</h3>
    <canvas id="monatsumsatzChart"></canvas>
    <script>
        var ctx = document.getElementById('monatsumsatzChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($result_monatsumsatz as $row) { echo "'" . $row['monat'] . "',"; } ?>],
                datasets: [{
                    label: 'Monatsumsatz (€)',
                    data: [<?php foreach ($result_monatsumsatz as $row) { echo $row['gesamtumsatz'] . ","; } ?>],
                    borderColor: 'blue',
                    fill: false
                }]
            }
        });
    </script>

    <h3>Umsatz je Wochentag</h3>
    <canvas id="wochentagsumsatzChart"></canvas>
    <script>
        var ctx = document.getElementById('wochentagsumsatzChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($result_wochentagsumsatz as $row) { echo "'" . $row['wochentag'] . "',"; } ?>],
                datasets: [{
                    label: 'Durchschnittlicher Umsatz (€)',
                    data: [<?php foreach ($result_wochentagsumsatz as $row) { echo $row['durchschnitt_umsatz'] . ","; } ?>],
                    backgroundColor: 'orange'
                }]
            }
        });
    </script>
  </main>

</body>
</html>
