<?php
require_once '../../includes/head.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/zeitraum_helpers.php';

// Session überprüfen
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$fahrer_id = $_SESSION['user_id'];

// Zeitraum-Logik
$zeitraum = $_GET['zeitraum'] ?? 'woche'; // Standard: Woche
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0; // Offset für vor/zurück
$periodError = null;

try {
    $periode = berechneZeitraum($zeitraum, ['offset' => $offset]);
} catch (InvalidArgumentException $e) {
    $periodError = $e->getMessage();
    $zeitraum = 'woche';
    $offset = 0;
    $periode = berechneZeitraum($zeitraum, ['offset' => $offset]);
}

$start_date = $periode['start_date'];
$end_date = $periode['end_date'];
$anzeige_zeitraum = $periode['label'] ?? (date('d.m.Y', strtotime($start_date)) . ' - ' . date('d.m.Y', strtotime($end_date)));

// Datenbankabfragen für Umsätze nach Tag
$stmt_umsatz_pro_tag = $pdo->prepare("
    SELECT 
        DATE(Datum) AS Datum, 
        SUM(TaxameterUmsatz + OhneTaxameter) AS GesamtUmsatz
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
    GROUP BY DATE(Datum)
    ORDER BY Datum ASC
");
$stmt_umsatz_pro_tag->execute([$fahrer_id, $start_date, $end_date]);
$umsatz_pro_tag = $stmt_umsatz_pro_tag->fetchAll(PDO::FETCH_ASSOC);

// Gesamtsumme direkt in der Datenbank ermitteln
$stmt_gesamt_umsatz = $pdo->prepare(
    'SELECT COALESCE(SUM(TaxameterUmsatz + OhneTaxameter), 0)
     FROM Umsatz
     WHERE FahrerID = ? AND Datum BETWEEN ? AND ?'
);
$stmt_gesamt_umsatz->execute([$fahrer_id, $start_date, $end_date]);
$gesamt_umsatz = (float) $stmt_gesamt_umsatz->fetchColumn();

// Datenbankabfragen für Umsätze nach Art
$stmt_umsatz_nach_art = $pdo->prepare("
    SELECT 
        SUM(TaxameterUmsatz + OhneTaxameter - Kartenzahlung - Rechnungsfahrten - Krankenfahrten - Gutscheine - Alita) AS Barzahlung,
        SUM(Kartenzahlung) AS Kartenzahlung,
        SUM(Rechnungsfahrten) AS Rechnungsfahrten,
        SUM(Krankenfahrten) AS Krankenfahrten,
		SUM(Gutscheine) AS Gutscheine,
		SUM(Alita) AS Alita
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
");
$stmt_umsatz_nach_art->execute([$fahrer_id, $start_date, $end_date]);
$umsatz_nach_art = $stmt_umsatz_nach_art->fetch(PDO::FETCH_ASSOC);

// Datenbankabfragen für Ausgaben nach Art
$stmt_ausgaben_nach_art = $pdo->prepare("
    SELECT 
        SUM(TankenWaschen) AS `Tanken und Waschen`,
        SUM(SonstigeAusgaben) AS sonstiges
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
");
$stmt_ausgaben_nach_art->execute([$fahrer_id, $start_date, $end_date]);
$ausgaben_nach_art = $stmt_ausgaben_nach_art->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiken</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/driver-dashboard.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/form-feedback.css">
</head>
<body>
    <?php include 'bottom_nav.php'; ?>

    <main>
        <h1>Statistiken</h1>

        <?php if ($periodError): ?>
            <div class="form-feedback form-feedback--error period-error">
                <?= htmlspecialchars($periodError) ?> Der ausgewählte Zeitraum wurde zurückgesetzt.
            </div>
        <?php endif; ?>

        <!-- Zeitraum-Auswahl -->
        <div class="zeitraum-navigation">
            <form method="GET" action="statistics.php" class="zeitraum-form">
                <button type="submit" name="offset" value="<?= $offset - 1 ?>" class="btn btn-navigation">
                    <i class="fa fa-chevron-left"></i> Zurück
                </button>
                <select name="zeitraum" onchange="this.form.submit()" class="dropdown">
                    <option value="tag" <?= $zeitraum === 'tag' ? 'selected' : '' ?>>Tag</option>
                    <option value="woche" <?= $zeitraum === 'woche' ? 'selected' : '' ?>>Woche</option>
                    <option value="monat" <?= $zeitraum === 'monat' ? 'selected' : '' ?>>Monat</option>
                    <option value="quartal" <?= $zeitraum === 'quartal' ? 'selected' : '' ?>>Quartal</option>
                    <option value="jahr" <?= $zeitraum === 'jahr' ? 'selected' : '' ?>>Jahr</option>
                </select>
                <button type="submit" name="offset" value="<?= $offset + 1 ?>" class="btn btn-navigation">
                    Vor <i class="fa fa-chevron-right"></i>
                </button>
            </form>
        </div>

        <!-- Aktueller Zeitraum -->
        <div class="zeitraum-anzeige">
            <h2>Zeitraum: <?= htmlspecialchars($anzeige_zeitraum) ?></h2>
        </div>

        <!-- Umsätze nach Tag -->
        <section>
            <h2>Umsätze nach Tag</h2>
            <table>
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Umsatz (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($umsatz_pro_tag as $eintrag): ?>
                        <?php $umsatz = $eintrag['GesamtUmsatz'] ?? 0; ?>
                        <tr>
                            <td><?= date("d.m.Y", strtotime($eintrag['Datum'])) ?></td>
                            <td><?= number_format($umsatz, 2, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Gesamt</th>
                        <th><?= number_format($gesamt_umsatz, 2, ',', '.') ?> €</th>
                    </tr>
                </tfoot>
            </table>
        </section>

        <!-- Umsätze nach Art -->
        <section>
            <h2>Umsätze nach Art</h2>
            <table>
                <thead>
                    <tr>
                        <th>Art</th>
                        <th>Betrag (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($umsatz_nach_art as $art => $betrag): ?>
                        <?php $betrag = $betrag ?? 0; ?>
                        <tr>
                            <td><?= ucfirst($art) ?></td>
                            <td><?= number_format($betrag, 2, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
		
		<!-- Ausgeben nach Art -->
		<section>
            <h2>Ausgaben nach Art</h2>
            <table>
                <thead>
                    <tr>
                        <th>Art</th>
                        <th>Betrag (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ausgaben_nach_art as $ausgaben_art => $betrag): ?>
						<?php $betrag = $betrag ?? 0; ?>
						<tr>
							<td><?= ucfirst($ausgaben_art) ?></td>
							<td><?= number_format($betrag, 2, ',', '.') ?> €</td>
						</tr>
					<?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
