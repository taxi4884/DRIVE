<?php
require_once '../../includes/bootstrap.php';
require_once '../../includes/driver_helpers.php';
require_once '../../includes/umsatz_repository.php';

// Fehleranzeige aktivieren (nur für Debugging, in Produktion entfernen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $fahrer_id = requireDriverId();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$umsatzRepository = new UmsatzRepository($pdo);

// Standardwerte
$zeitraum = $_GET['zeitraum'] ?? 'woche'; // Standard: Woche
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0; // Offset für vor/zurück

// Zeitraum-Logik
$heute = date('Y-m-d');
switch ($zeitraum) {
    case 'tag':
        $start_date = date('Y-m-d', strtotime("$offset day"));
        $end_date = $start_date;
        $anzeige_zeitraum = date("d.m.y", strtotime($start_date));
        break;

    case 'woche':
        $start_date = date('Y-m-d', strtotime("monday this week +$offset week"));
        $end_date = date('Y-m-d', strtotime("sunday this week +$offset week"));
        $anzeige_zeitraum = date("d.m.y", strtotime($start_date)) . " - " . date("d.m.y", strtotime($end_date));
        break;

    case 'monat':
        $start_date = date('Y-m-01', strtotime("$offset month"));
        $end_date = date('Y-m-t', strtotime("$offset month"));
        $anzeige_zeitraum = date("d.m.y", strtotime($start_date)) . " - " . date("d.m.y", strtotime($end_date));
        break;

    case 'quartal':
        $aktuelles_quartal = ceil(date('n') / 3) + $offset;
        $jahr = date('Y', strtotime("$offset quarter"));
        $start_month = (($aktuelles_quartal - 1) * 3) + 1;
        $start_date = date('Y-m-d', mktime(0, 0, 0, $start_month, 1, $jahr));
        $end_date = date('Y-m-t', mktime(0, 0, 0, $start_month + 2, 1, $jahr));
        $anzeige_zeitraum = date("d.m.y", strtotime($start_date)) . " - " . date("d.m.y", strtotime($end_date));
        break;

    case 'jahr':
        $jahr = date('Y') + $offset;
        $start_date = "$jahr-01-01";
        $end_date = "$jahr-12-31";
        $anzeige_zeitraum = "$jahr";
        break;

    default:
        $zeitraum = 'woche';
        $start_date = date('Y-m-d', strtotime("monday this week +$offset week"));
        $end_date = date('Y-m-d', strtotime("sunday this week +$offset week"));
        $anzeige_zeitraum = date("d.m.y", strtotime($start_date)) . " - " . date("d.m.y", strtotime($end_date));
        break;
}

$umsatz_daten = $umsatzRepository->getByDriverAndRange($fahrer_id, $start_date, $end_date);
$umsatz_pro_tag = UmsatzRepository::aggregateByDate($umsatz_daten);
$umsatz_nach_art = UmsatzRepository::aggregateByType($umsatz_daten);
$ausgaben_nach_art = UmsatzRepository::aggregateExpenses($umsatz_daten);

$gesamt_umsatz = array_reduce(
    $umsatz_pro_tag,
    static fn($carry, $eintrag) => $carry + (float)($eintrag['GesamtUmsatz'] ?? 0),
    0.0
);

$title = 'Statistiken';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
    'css/driver-dashboard.css'
];
include __DIR__ . '/../../includes/layout.php';
?>
    <main>
        <h1>Statistiken</h1>

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
                            <td><?= date("d.m.y", strtotime($eintrag['Datum'])) ?></td>
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
