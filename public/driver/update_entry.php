<?php
require_once '../../includes/bootstrap.php'; // Datenbankverbindung

// Rolle für diese Route festlegen (einfachste Variante)
$_SESSION['rolle'] = 'Fahrer';

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

// Überprüfung, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    die('Fehler: Keine gültige Session. Bitte erneut anmelden.');
}

// Fahrer-ID aus der Session
$fahrer_id = $_SESSION['user_id'];

// Abfrage des Eintrags aus der Datenbank
$stmt = $pdo->prepare("
    SELECT * 
    FROM Umsatz 
    WHERE Datum = ? AND FahrerID = ?
");
$stmt->execute([$datum, $fahrer_id]);
$eintrag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eintrag) {
    die('Eintrag nicht gefunden.');
}

// Update-Logik
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taxameter_umsatz = (float)($_POST['taxameter_umsatz'] ?? 0);
    $ohne_taxameter = (float)($_POST['ohne_taxameter'] ?? 0);
    $kartenzahlung = (float)($_POST['kartenzahlung'] ?? 0);
    $rechnungsfahrten = (float)($_POST['rechnungsfahrten'] ?? 0);
    $krankenfahrten = (float)($_POST['krankenfahrten'] ?? 0);
    $gutscheine = (float)($_POST['gutscheine'] ?? 0);
    $alita = (float)($_POST['alita'] ?? 0);
    $tanken_waschen = (float)($_POST['tanken_waschen'] ?? 0);
    $sonstige_ausgaben = (float)($_POST['sonstige_ausgaben'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            UPDATE Umsatz 
            SET TaxameterUmsatz = ?, OhneTaxameter = ?, Kartenzahlung = ?, Rechnungsfahrten = ?, 
                Krankenfahrten = ?, Gutscheine = ?, Alita = ?, TankenWaschen = ?, SonstigeAusgaben = ?
            WHERE FahrerID = ? AND Datum = ?
        ");

        $stmt->execute([
            $taxameter_umsatz, $ohne_taxameter, $kartenzahlung, $rechnungsfahrten,
            $krankenfahrten, $gutscheine, $alita, $tanken_waschen, $sonstige_ausgaben,
            $fahrer_id, $datum
        ]);

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
	<script>
        // Automatische Berechnung des Gesamtumsatzes
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', calculateTotal);
        });

        function calculateTotal() {
            const taxameter = parseFloat(document.getElementById('taxameter').value) || 0;
            const ohne_taxameter = parseFloat(document.getElementById('ohne_taxameter').value) || 0;
            const kartenzahlung = parseFloat(document.getElementById('kartenzahlung').value) || 0;
            const rechnungsfahrten = parseFloat(document.getElementById('rechnungsfahrten').value) || 0;
            const krankenfahrten = parseFloat(document.getElementById('krankenfahrten').value) || 0;
            const gutscheine = parseFloat(document.getElementById('gutscheine').value) || 0;
            const alita = parseFloat(document.getElementById('alita').value) || 0;
            const tanken_waschen = parseFloat(document.getElementById('tanken_waschen').value) || 0;
            const sonstige_ausgaben = parseFloat(document.getElementById('sonstige_ausgaben').value) || 0;

            const total = taxameter + ohne_taxameter - kartenzahlung - rechnungsfahrten -
                          krankenfahrten - gutscheine - alita - tanken_waschen - sonstige_ausgaben;

            document.getElementById('gesamtumsatz').value = total.toFixed(2);
        }
    </script>
</body>
</html>
