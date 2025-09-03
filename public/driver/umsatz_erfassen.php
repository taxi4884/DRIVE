<?php
require_once '../../includes/bootstrap.php';

// Rolle für diese Route festlegen (einfachste Variante)
$_SESSION['rolle'] = 'Fahrer';

if ($_SESSION['user_role'] !== 'fahrer') {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

$fahrer_id = $_SESSION['user_id'];

// Prüfung: Offene Schichten (Schichten mit Anmeldung, aber ohne Umsatz)
$offene_daten = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT DATE(sfa.anmeldung) AS offenes_datum
    FROM sync_fahreranmeldung sfa
    JOIN Fahrer f 
        ON sfa.fahrer = f.Fahrernummer OR sfa.fahrer = f.fms_alias
    WHERE f.FahrerID = :fahrer_id
      AND DATE(sfa.anmeldung) NOT IN (
          SELECT DATE(Datum) FROM Umsatz WHERE FahrerID = :fahrer_id
      )
    ORDER BY offenes_datum DESC
");
$stmt->execute(['fahrer_id' => $fahrer_id]);
$offene_daten = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fahrer_id = $_SESSION['user_id'];
    $datum = $_POST['datum'];
    $taxameter_umsatz = floatval($_POST['taxameter_umsatz'] ?? 0);
    $ohne_taxameter = floatval($_POST['ohne_taxameter'] ?? 0);
    $kartenzahlung = floatval($_POST['kartenzahlung'] ?? 0);
    $rechnungsfahrten = floatval($_POST['rechnungsfahrten'] ?? 0);
    $krankenfahrten = floatval($_POST['krankenfahrten'] ?? 0);
    $gutscheine = floatval($_POST['gutscheine'] ?? 0);
    $alita = floatval($_POST['alita'] ?? 0);
    $tanken_waschen = floatval($_POST['tanken_waschen'] ?? 0);
    $sonstige_ausgaben = floatval($_POST['sonstige_ausgaben'] ?? 0);
    $notiz = $_POST['notiz'] ?? null;

    try {
        $fahrer_stmt = $pdo->prepare("SELECT Vorname, Nachname FROM Fahrer WHERE FahrerID = ?");
        $fahrer_stmt->execute([$fahrer_id]);
        $fahrer = $fahrer_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fahrer) {
            throw new Exception("Fahrer nicht gefunden.");
        }

        $vorname = $fahrer['Vorname'];
        $nachname = $fahrer['Nachname'];

		if ($taxameter_umsatz <= 0 && $ohne_taxameter <= 0) {
			throw new Exception("Bitte mindestens einen Umsatz (mit oder ohne Taxameter) eingeben.");
		}

        $umsatz = $taxameter_umsatz + $ohne_taxameter;

        $umsatz_stmt = $pdo->prepare("
            INSERT INTO Umsatz (
                FahrerID,
                Datum,
                TaxameterUmsatz,
                OhneTaxameter,
                Kartenzahlung,
                Rechnungsfahrten,
                Krankenfahrten,
                Gutscheine,
                Alita,
                TankenWaschen,
                SonstigeAusgaben,
                Notiz
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $umsatz_stmt->execute([
            $fahrer_id,
            $datum,
            $taxameter_umsatz,
            $ohne_taxameter,
            $kartenzahlung,
            $rechnungsfahrten,
            $krankenfahrten,
            $gutscheine,
            $alita,
            $tanken_waschen,
            $sonstige_ausgaben,
            $notiz
        ]);

        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications (
                Vorname,
                Nachname,
                Umsatz,
                Datum,
                gesendet
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $notification_stmt->execute([
            $vorname,
            $nachname,
            $umsatz,
            $datum,
            0
        ]);

        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        $error = "Fehler: " . $e->getMessage();
    }
}

$title = 'Umsatz erfassen';
$extraCss = ['css/driver-dashboard.css'];
include __DIR__ . '/../../includes/layout.php';
?>
    <style>
        fieldset {
            border: 2px solid #ccc;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        .gruen { background-color: #e8f5e9; }
        .blau  { background-color: #e3f2fd; }
        .rot   { background-color: #ffebee; }
        .gelb  { background-color: #fffde7; }

        legend {
            font-weight: bold;
        }

        form, label, legend, textarea, input {
            text-align: left;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input[type="number"], input[type="date"], textarea {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
        }

        textarea {
            vertical-align: top;
        }
		#overlay {
			position: fixed;
			top: 0; left: 0; right: 0; bottom: 0;
			background: rgba(255, 255, 255, 0.95);
			z-index: 9999;
			display: none;
			align-items: center;
			justify-content: center;
			flex-direction: column;
		}

		.taxi-wrapper {
			position: relative;
			width: 100vw;
			height: 80px;
			overflow: hidden;
		}

		.taxi {
			width: 60px;
			height: 60px;
			background-image: url('https://em-content.zobj.net/source/microsoft/310/taxi_1f695.png');
			background-size: contain;
			background-repeat: no-repeat;
			position: absolute;
			top: 10px;
			left: 100%;
			animation: drive 2s linear infinite;
		}

		.puste {
			font-size: 40px;
			position: absolute;
			top: 20px;
			right: calc(100% + 30px); /* Start weiter rechts */
			animation: blink 1s infinite, drivePuste 2s linear infinite;
		}

		@keyframes drive {
			0% { left: 100%; }
			100% { left: -100px; }
		}

		@keyframes drivePuste {
			0% { left: calc(100% + 50px); }
			100% { left: -50px; }
		}

		@keyframes blink {
			0%, 100% {
				opacity: 1;
				transform: scale(1);
			}
			25%, 75% {
				opacity: 0.4;
				transform: scale(1.2);
			}
			50% {
				opacity: 1;
				transform: scale(1);
			}
		}

		#overlay p {
			font-weight: bold;
			font-size: 18px;
			color: #333;
			margin-top: 20px;
		}
    </style>
        <main>
		<h1>Umsatz erfassen</h1>
		<?php if ($error): ?>
			<p style="color: red;"><?= htmlspecialchars($error) ?></p>
		<?php endif; ?>

		<form id="umsatzForm" action="umsatz_erfassen.php" method="POST">
			<?php
			$aktuelleStunde = (int)date('H');
			if ($aktuelleStunde < 6) {
				echo '<p style="color: orange; font-weight: bold;">⚠️ Bitte achte auf das richtige Datum! (Aktuell vor 06:00 Uhr)</p>';
			}
			?>
			
			<label for="datum">Datum (offene Schichten):</label>
			<?php if (empty($offene_daten)): ?>
				<input type="date" id="datum" name="datum" value="<?= htmlspecialchars($_POST['datum'] ?? date('Y-m-d')) ?>" required>
				<p class="hinweis">⚠️ Keine offene Schicht gefunden – bitte Datum manuell prüfen.</p>
			<?php else: ?>
				<select name="datum" id="datum" required>
					<?php foreach ($offene_daten as $datum): ?>
						<option value="<?= htmlspecialchars($datum) ?>" <?= (isset($_POST['datum']) && $_POST['datum'] === $datum) ? 'selected' : '' ?>>
							<?= htmlspecialchars($datum) ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<fieldset class="gruen">
				<legend>Bargeld-Einnahmen</legend>
				
				<label for="taxameter">Umsatz mit Taxameter (€):</label>
				<input type="number" id="taxameter" name="taxameter_umsatz" step="0.01" min="0" value="<?= htmlspecialchars($_POST['taxameter_umsatz'] ?? '') ?>">

				<label for="ohne_taxameter">Umsatz ohne Taxameter (€):</label>
				<input type="number" id="ohne_taxameter" name="ohne_taxameter" step="0.01" min="0" value="<?= htmlspecialchars($_POST['ohne_taxameter'] ?? '') ?>">
			</fieldset>

			<fieldset class="blau">
				<legend>Bargeldlose Umsätze</legend>

				<label for="kartenzahlung">Kartenzahlungen (€):</label>
				<input type="number" id="kartenzahlung" name="kartenzahlung" step="0.01" min="0" value="<?= htmlspecialchars($_POST['kartenzahlung'] ?? '') ?>">

				<label for="rechnungsfahrten">Rechnungsfahrten (€):</label>
				<input type="number" id="rechnungsfahrten" name="rechnungsfahrten" step="0.01" min="0" value="<?= htmlspecialchars($_POST['rechnungsfahrten'] ?? '') ?>">

				<label for="krankenfahrten">Krankenfahrten ohne Zuzahlung (€):</label>
				<input type="number" id="krankenfahrten" name="krankenfahrten" step="0.01" min="0" value="<?= htmlspecialchars($_POST['krankenfahrten'] ?? '') ?>">

				<label for="gutscheine">Gutscheine (€):</label>
				<input type="number" id="gutscheine" name="gutscheine" step="0.01" min="0" value="<?= htmlspecialchars($_POST['gutscheine'] ?? '') ?>">

				<label for="alita">Alita (€):</label>
				<input type="number" id="alita" name="alita" step="0.01" min="0" value="<?= htmlspecialchars($_POST['alita'] ?? '') ?>">
			</fieldset>

			<fieldset class="rot">
				<legend>Ausgaben</legend>

				<label for="tanken_waschen">Tanken/Waschen (€):</label>
				<input type="number" id="tanken_waschen" name="tanken_waschen" step="0.01" min="0" value="<?= htmlspecialchars($_POST['tanken_waschen'] ?? '') ?>">

				<label for="sonstige_ausgaben">Sonstige Ausgaben (€):</label>
				<input type="number" id="sonstige_ausgaben" name="sonstige_ausgaben" step="0.01" min="0" value="<?= htmlspecialchars($_POST['sonstige_ausgaben'] ?? '') ?>">
			</fieldset>

			<fieldset class="gelb">
				<legend>Übriges Bargeld</legend>

				<label for="gesamtumsatz">Bargeld (€):</label>
				<input type="text" id="gesamtumsatz" readonly>
			</fieldset>

			<label for="notiz">Notiz (optional):</label>
			<textarea id="notiz" name="notiz" rows="4" cols="50"><?= htmlspecialchars($_POST['notiz'] ?? '') ?></textarea>

			<button type="submit">Umsatz speichern</button>
		</form>
		
		<div id="overlay">
			<div class="taxi-wrapper">
				<div class="puste">💨</div>
				<div class="taxi"></div>
			</div>
			<p>Umsatz wird gespeichert...</p>
		</div>

	</main>

	<?php include 'nav-script.php'; ?>

<script>
// --------- Summen‑ und Eingabefunktionen ---------
function calculateTotal() {
    const taxameter        = parseFloat(document.getElementById('taxameter').value)        || 0;
    const ohneTaxameter    = parseFloat(document.getElementById('ohne_taxameter').value)   || 0;
    const kartenzahlung    = parseFloat(document.getElementById('kartenzahlung').value)    || 0;
    const rechnungsfahrten = parseFloat(document.getElementById('rechnungsfahrten').value) || 0;
    const krankenfahrten   = parseFloat(document.getElementById('krankenfahrten').value)   || 0;
    const gutscheine       = parseFloat(document.getElementById('gutscheine').value)       || 0;
    const alita            = parseFloat(document.getElementById('alita').value)            || 0;
    const tankenWaschen    = parseFloat(document.getElementById('tanken_waschen').value)   || 0;
    const sonstigeAusgaben = parseFloat(document.getElementById('sonstige_ausgaben').value)|| 0;

    // Hier nur Beispiel‑Logik – falls du etwas anderes brauchst, Formel anpassen
    const total = taxameter + ohneTaxameter
                - kartenzahlung - rechnungsfahrten - krankenfahrten
                - gutscheine - alita - tankenWaschen - sonstigeAusgaben;

    document.getElementById('gesamtumsatz').value = total.toFixed(2);
}

function validateInput(input) {
    input.style.border =
        input.value && parseFloat(input.value) >= 0
        ? '2px solid #4caf50'
        : '2px solid #ccc';
}

document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', () => {
        calculateTotal();
        validateInput(input);
    });
});

// --------- Formular‑Handling ---------
const form    = document.getElementById('umsatzForm');
const overlay = document.getElementById('overlay');

function mindestensEinUmsatz() {
    const tax  = parseFloat(document.getElementById('taxameter').value)      || 0;
    const ohne = parseFloat(document.getElementById('ohne_taxameter').value) || 0;

    if (tax === 0 && ohne === 0) {
        alert('Bitte gib entweder einen Umsatz *mit* oder *ohne* Taxameter ein.');
        return false;
    }
    return true;
}

form.addEventListener('submit', function (event) {
    event.preventDefault();                 // Standard‑Submit unterbinden

    if (!mindestensEinUmsatz()) {           // Validierung
        return;                             // Abbruch ohne Overlay
    }

    overlay.style.display = 'flex';         // Animation anzeigen
    setTimeout(() => form.submit(), 2000);  // nach 2 s wirklich senden
});
</script>

</body>
</html>
