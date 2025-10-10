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
$where_clause = " WHERE f.Aktiv = 1";
if ($selected_fahrer) {
    $where_clause .= " AND u.FahrerID = :fahrerID";
}

// Array zur Übersetzung der Wochentage
$wochentage_deutsch = [
    'Monday' => 'Montag',
    'Tuesday' => 'Dienstag',
    'Wednesday' => 'Mittwoch',
    'Thursday' => 'Donnerstag',
    'Friday' => 'Freitag',
    'Saturday' => 'Samstag',
    'Sunday' => 'Sonntag'
];

// Durchschnittlicher Umsatz pro Schicht (gesamt und pro Fahrer)
$sql_umsatz_schicht = "SELECT ff.Schicht AS Schichtart, AVG(u.TaxameterUmsatz + u.OhneTaxameter) AS durchschnitt_umsatz
                        FROM Umsatz u
                        JOIN FahrerFahrzeug ff ON u.FahrerID = ff.FahrerID
                        JOIN Fahrer f ON u.FahrerID = f.FahrerID
                        $where_clause
                        GROUP BY ff.Schicht";
$stmt_umsatz_schicht = $pdo->prepare($sql_umsatz_schicht);
if ($selected_fahrer) {
    $stmt_umsatz_schicht->bindParam(':fahrerID', $selected_fahrer, PDO::PARAM_INT);
}
$stmt_umsatz_schicht->execute();
$result_umsatz_schicht = $stmt_umsatz_schicht->fetchAll(PDO::FETCH_ASSOC);

// Gesamtumsatz pro Fahrer und Monat
$sql_umsatz_fahrer_monate = "SELECT f.Vorname, f.Nachname, DATE_FORMAT(u.Datum, '%Y-%m') AS monat,
                             SUM(u.TaxameterUmsatz + u.OhneTaxameter) AS gesamtumsatz
                             FROM Umsatz u
                             JOIN Fahrer f ON u.FahrerID = f.FahrerID
                             WHERE f.Aktiv = 1
                             GROUP BY f.FahrerID, monat
                             ORDER BY f.Nachname, f.Vorname, monat";
$stmt_umsatz_fahrer_monate = $pdo->prepare($sql_umsatz_fahrer_monate);
$stmt_umsatz_fahrer_monate->execute();
$result_umsatz_fahrer_monate = $stmt_umsatz_fahrer_monate->fetchAll(PDO::FETCH_ASSOC);

// Umsätze in ein strukturiertes Array umwandeln
$umsatz_daten = [];
$monate = [];
foreach ($result_umsatz_fahrer_monate as $row) {
    $fahrer_name = $row['Vorname'] . ' ' . $row['Nachname'];
    $monat = $row['monat'];
    $umsatz_daten[$fahrer_name][$monat] = $row['gesamtumsatz'];
    $monate[$monat] = true;
}

// Sortierung der Monate
ksort($monate);

// Arbeitstage je Fahrer im Monat
$sql_arbeitstage = "SELECT f.Vorname, f.Nachname, COUNT(DISTINCT DATE(u.Datum)) AS arbeitstage
                    FROM Umsatz u
                    JOIN Fahrer f ON u.FahrerID = f.FahrerID
                    $where_clause
                    GROUP BY u.FahrerID
                    ORDER BY arbeitstage DESC";
$stmt_arbeitstage = $pdo->prepare($sql_arbeitstage);
if ($selected_fahrer) {
    $stmt_arbeitstage->bindParam(':fahrerID', $selected_fahrer, PDO::PARAM_INT);
}
$stmt_arbeitstage->execute();
$result_arbeitstage = $stmt_arbeitstage->fetchAll(PDO::FETCH_ASSOC);

// Durchschnittlicher Umsatz pro Wochentag je Fahrer
$sql_wochentage = "SELECT f.Vorname, f.Nachname, DAYNAME(u.Datum) AS wochentag,
                           AVG(u.TaxameterUmsatz + u.OhneTaxameter) AS durchschnitt_umsatz
                    FROM Umsatz u
                    JOIN Fahrer f ON u.FahrerID = f.FahrerID
                    WHERE f.Aktiv = 1
                    GROUP BY f.FahrerID, wochentag
                    ORDER BY f.Nachname, f.Vorname";
$stmt_wochentage = $pdo->prepare($sql_wochentage);
$stmt_wochentage->execute();
$result_wochentage = $stmt_wochentage->fetchAll(PDO::FETCH_ASSOC);

// Datenstruktur für Wochentage
$umsatz_wochentage = [];
foreach ($result_wochentage as $row) {
    $fahrer_name = $row['Vorname'] . ' ' . $row['Nachname'];
    $wochentag = $wochentage_deutsch[$row['wochentag']] ?? $row['wochentag'];
    $umsatz_wochentage[$fahrer_name][$wochentag] = $row['durchschnitt_umsatz'];
}

// Effizienteste Fahrer: Umsatz pro Arbeitstag
$sql_effizienz = "SELECT f.Vorname, f.Nachname,
                        SUM(u.TaxameterUmsatz + u.OhneTaxameter) / COUNT(DISTINCT DATE(u.Datum)) AS umsatz_pro_arbeitstag
                  FROM Umsatz u
                  JOIN Fahrer f ON u.FahrerID = f.FahrerID
                  $where_clause
                  GROUP BY u.FahrerID
                  ORDER BY umsatz_pro_arbeitstag DESC";
$stmt_effizienz = $pdo->prepare($sql_effizienz);
if ($selected_fahrer) {
    $stmt_effizienz->bindParam(':fahrerID', $selected_fahrer, PDO::PARAM_INT);
}
$stmt_effizienz->execute();
$result_effizienz = $stmt_effizienz->fetchAll(PDO::FETCH_ASSOC);

// Durchschnittlicher Umsatz pro Monat je Fahrer (Top 10)
$sql_durchschnitt_umsatz_monat = "SELECT f.Vorname, f.Nachname,
                                    SUM(u.TaxameterUmsatz + u.OhneTaxameter) / COUNT(DISTINCT DATE_FORMAT(u.Datum, '%Y-%m')) AS durchschnitt_umsatz
                                  FROM Umsatz u
                                  JOIN Fahrer f ON u.FahrerID = f.FahrerID
                                  WHERE f.Aktiv = 1
                                  GROUP BY f.FahrerID
                                  ORDER BY durchschnitt_umsatz DESC
                                  LIMIT 10";
$stmt_durchschnitt_umsatz_monat = $pdo->prepare($sql_durchschnitt_umsatz_monat);
$stmt_durchschnitt_umsatz_monat->execute();
$result_durchschnitt_umsatz_monat = $stmt_durchschnitt_umsatz_monat->fetchAll(PDO::FETCH_ASSOC);

// Fahrer mit durchschnittlichem Umsatz pro Arbeitstag unter 264 Euro
$sql_fahrer_unter_264 = "SELECT f.Vorname, f.Nachname,
                            SUM(u.TaxameterUmsatz + u.OhneTaxameter) / COUNT(DISTINCT DATE(u.Datum)) AS durchschnitt_umsatz_tag
                          FROM Umsatz u
                          JOIN Fahrer f ON u.FahrerID = f.FahrerID
                          WHERE f.Aktiv = 1
                          GROUP BY f.FahrerID
                          HAVING durchschnitt_umsatz_tag < 264
                          ORDER BY durchschnitt_umsatz_tag ASC";
$stmt_fahrer_unter_264 = $pdo->prepare($sql_fahrer_unter_264);
$stmt_fahrer_unter_264->execute();
$result_fahrer_unter_264 = $stmt_fahrer_unter_264->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$title = 'Fahrerstatistik';
include __DIR__ . '/../includes/layout.php';
?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/modal.js"></script>

	    <main>
    <h2>Fahrerstatistik</h2>
    <p>Hier sind die Statistiken zu den Fahrern basierend auf Umsatz, Effizienz und Arbeitstagen.</p>
    
    <h3>Durchschnittlicher Umsatz pro Schicht</h3>
    <table>
        <tr>
            <th>Schicht</th>
            <th>Durchschnittlicher Umsatz (€)</th>
        </tr>
        <?php foreach ($result_umsatz_schicht as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['Schichtart']) ?></td>
            <td><?= number_format($row['durchschnitt_umsatz'], 2) ?> €</td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3>Gesamtumsatz pro Fahrer je Monat</h3>
    <table>
        <tr>
            <th>Fahrer</th>
            <?php foreach ($monate as $monat => $_): ?>
                <th><?= htmlspecialchars($monat) ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($umsatz_daten as $fahrer => $umsatz): ?>
        <tr>
            <td><?= htmlspecialchars($fahrer) ?></td>
            <?php foreach ($monate as $monat => $_): ?>
                <td><?= isset($umsatz[$monat]) ? number_format($umsatz[$monat], 2) . ' €' : '-' ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3>Arbeitstage je Fahrer</h3>
    <table>
        <tr>
            <th>Fahrer</th>
            <th>Arbeitstage</th>
        </tr>
        <?php foreach ($result_arbeitstage as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['Vorname'] . ' ' . $row['Nachname']) ?></td>
            <td><?= $row['arbeitstage'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3>Durchschnittlicher Umsatz je Wochentag</h3>
    <table>
        <tr>
            <th>Fahrer</th>
            <?php foreach ($wochentage_deutsch as $wochentag): ?>
                <th><?= htmlspecialchars($wochentag) ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($umsatz_wochentage as $fahrer => $umsatz): ?>
        <tr>
            <td><?= htmlspecialchars($fahrer) ?></td>
            <?php foreach ($wochentage_deutsch as $wochentag): ?>
                <td><?= isset($umsatz[$wochentag]) ? number_format($umsatz[$wochentag], 2) . ' €' : '-' ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3>Effizienteste Fahrer (Umsatz pro Arbeitstag)</h3>
    <table>
        <tr>
            <th>Fahrer</th>
            <th>Durchschnittlicher Umsatz pro Arbeitstag (€)</th>
        </tr>
        <?php foreach ($result_effizienz as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['Vorname'] . ' ' . $row['Nachname']) ?></td>
            <td><?= number_format($row['umsatz_pro_arbeitstag'], 2) ?> €</td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3>Top-Fahrer</h3>
    <table>
        <tr>
            <th>Fahrer</th>
            <th>Durchschnittlicher Monatsumsatz (€)</th>
        </tr>
        <?php foreach ($result_durchschnitt_umsatz_monat as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['Vorname'] . ' ' . $row['Nachname']) ?></td>
            <td><?= number_format($row['durchschnitt_umsatz'], 2) ?> €</td>
        </tr>
        <?php endforeach; ?>
    </table>
	
	<h3>Fahrer mit durchschnittlichem Tagesumsatz unter 264 €</h3>
    <table>
        <tr>
            <th>Fahrer</th>
            <th>Durchschnittlicher Tagesumsatz (€)</th>
        </tr>
        <?php foreach ($result_fahrer_unter_264 as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['Vorname'] . ' ' . $row['Nachname']) ?></td>
            <td><?= number_format($row['durchschnitt_umsatz_tag'], 2) ?> €</td>
        </tr>
        <?php endforeach; ?>
    </table>
</main>
</main>

</body>
</html>
