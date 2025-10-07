<?php
require_once '../../includes/bootstrap.php'; // Datenbankverbindung
require_once '../../includes/driver_helpers.php';
require_once '../../includes/umsatz_repository.php';

// Fehleranzeige aktivieren (nur für Debugging, in Produktion entfernen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Überprüfung, ob der Parameter existiert
if (!isset($_GET['datum']) || empty($_GET['datum'])) {
    die('Eintrag nicht gefunden. Kein Datum übergeben.');
}

// Datum direkt aus dem Parameter abrufen
$datum = $_GET['datum'];

try {
    $fahrer_id = requireDriverId();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$umsatzRepository = new UmsatzRepository($pdo);
$eintrag = $umsatzRepository->getByDriverAndDate($fahrer_id, $datum);

if (!$eintrag) {
    die('Eintrag nicht gefunden.');
}

// Update-Logik
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'TaxameterUmsatz' => (float)($_POST['taxameter_umsatz'] ?? 0),
        'OhneTaxameter' => (float)($_POST['ohne_taxameter'] ?? 0),
        'Kartenzahlung' => (float)($_POST['kartenzahlung'] ?? 0),
        'Rechnungsfahrten' => (float)($_POST['rechnungsfahrten'] ?? 0),
        'Krankenfahrten' => (float)($_POST['krankenfahrten'] ?? 0),
        'Gutscheine' => (float)($_POST['gutscheine'] ?? 0),
        'Alita' => (float)($_POST['alita'] ?? 0),
        'TankenWaschen' => (float)($_POST['tanken_waschen'] ?? 0),
        'SonstigeAusgaben' => (float)($_POST['sonstige_ausgaben'] ?? 0),
    ];

    try {
        $umsatzRepository->update($fahrer_id, $datum, $payload);
        $eintrag = array_merge($eintrag, $payload);
        $success = "Eintrag erfolgreich aktualisiert!";
    } catch (PDOException $e) {
        $error = 'Fehler beim Aktualisieren des Eintrags: ' . $e->getMessage();
    }
}

$title = 'Umsatz bearbeiten';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
    'css/driver-dashboard.css'
];
include __DIR__ . '/../../includes/layout.php';
?>
    <main>
        <h1>Umsatz bearbeiten</h1>
        <?php if ($error): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="color: green;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form action="update_entry.php?datum=<?= htmlspecialchars($datum) ?>" method="POST">
			<label for="datum">Datum:</label>
			<input type="text" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" readonly>
			<br>

			<label for="taxameter">Umsatz mit Taxameter (€):</label>
			<input type="number" id="taxameter" name="taxameter_umsatz" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['TaxameterUmsatz']) ?>" required>
			<br>

			<label for="ohne_taxameter">Umsatz ohne Taxameter (€):</label>
			<input type="number" id="ohne_taxameter" name="ohne_taxameter" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['OhneTaxameter']) ?>" required>
			<br>

			<label for="kartenzahlung">Kartenzahlungen (€):</label>
			<input type="number" id="kartenzahlung" name="kartenzahlung" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['Kartenzahlung']) ?>" required>
			<br>

			<label for="rechnungsfahrten">Rechnungsfahrten (€):</label>
			<input type="number" id="rechnungsfahrten" name="rechnungsfahrten" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['Rechnungsfahrten']) ?>" required>
			<br>

			<label for="krankenfahrten">Krankenfahrten ohne Zuzahlung (€):</label>
			<input type="number" id="krankenfahrten" name="krankenfahrten" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['Krankenfahrten']) ?>" required>
			<br>

			<label for="gutscheine">Gutscheine (€):</label>
			<input type="number" id="gutscheine" name="gutscheine" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['Gutscheine']) ?>" required>
			<br>

			<label for="alita">Alita (€):</label>
			<input type="number" id="alita" name="alita" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['Alita']) ?>" required>
			<br>

			<label for="tanken_waschen">Tanken/Waschen (€):</label>
			<input type="number" id="tanken_waschen" name="tanken_waschen" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['TankenWaschen']) ?>" required>
			<br>

			<label for="sonstige_ausgaben">Sonstige Ausgaben (€):</label>
			<input type="number" id="sonstige_ausgaben" name="sonstige_ausgaben" step="0.01" min="0" value="<?= htmlspecialchars($eintrag['SonstigeAusgaben']) ?>" required>
			<br>

            <label for="gesamtumsatz">Bargeld (€):</label>
            <input type="text" id="gesamtumsatz" readonly>
            <br>
			
			<button type="submit">Eintrag aktualisieren</button>
		</form>
    </main>
        <script src="../js/driver-cash-calculator.js"></script>
        <script>
        DriverCashCalculator.init({
            incomeFields: ['taxameter', 'ohne_taxameter'],
            expenseFields: ['kartenzahlung', 'rechnungsfahrten', 'krankenfahrten', 'gutscheine', 'alita', 'tanken_waschen', 'sonstige_ausgaben'],
            outputField: '#gesamtumsatz'
        });
        </script>
</body>
</html>
