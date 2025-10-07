<?php
require_once '../../includes/bootstrap.php';
require_once '../../includes/driver_helpers.php';
require_once '../../includes/umsatz_repository.php';

$error = '';
$success = '';

try {
    $fahrer_id = requireDriverId();
    $fahrer = fetchDriverProfile($pdo, $fahrer_id);
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$umsatzRepository = new UmsatzRepository($pdo);
$offene_daten = $umsatzRepository->findOpenShiftDates($fahrer_id);

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datum = $_POST['datum'];
    $payload = [
        'Datum' => $datum,
        'TaxameterUmsatz' => (float)($_POST['taxameter_umsatz'] ?? 0),
        'OhneTaxameter' => (float)($_POST['ohne_taxameter'] ?? 0),
        'Kartenzahlung' => (float)($_POST['kartenzahlung'] ?? 0),
        'Rechnungsfahrten' => (float)($_POST['rechnungsfahrten'] ?? 0),
        'Krankenfahrten' => (float)($_POST['krankenfahrten'] ?? 0),
        'Gutscheine' => (float)($_POST['gutscheine'] ?? 0),
        'Alita' => (float)($_POST['alita'] ?? 0),
        'TankenWaschen' => (float)($_POST['tanken_waschen'] ?? 0),
        'SonstigeAusgaben' => (float)($_POST['sonstige_ausgaben'] ?? 0),
        'Notiz' => $_POST['notiz'] ?? null,
    ];

    try {
        if ($payload['TaxameterUmsatz'] <= 0 && $payload['OhneTaxameter'] <= 0) {
            throw new Exception('Bitte mindestens einen Umsatz (mit oder ohne Taxameter) eingeben.');
        }

        $umsatzRepository->create($fahrer_id, $payload);

        $umsatz = $payload['TaxameterUmsatz'] + $payload['OhneTaxameter'];
        $notification_stmt = $pdo->prepare(
            'INSERT INTO notifications (Vorname, Nachname, Umsatz, Datum, gesendet) VALUES (?, ?, ?, ?, ?)'
        );
        $notification_stmt->execute([
            $fahrer['Vorname'],
            $fahrer['Nachname'],
            $umsatz,
            $datum,
            0,
        ]);

        header('Location: dashboard.php');
        exit();
    } catch (Exception $e) {
        $error = 'Fehler: ' . $e->getMessage();
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

<script src="../js/driver-cash-calculator.js"></script>
<script>
const calculator = DriverCashCalculator.init({
    incomeFields: ['taxameter', 'ohne_taxameter'],
    expenseFields: ['kartenzahlung', 'rechnungsfahrten', 'krankenfahrten', 'gutscheine', 'alita', 'tanken_waschen', 'sonstige_ausgaben'],
    outputField: '#gesamtumsatz',
    onUpdate({ elements }) {
        Object.values(elements).forEach((input) => {
            if (!input || input.type !== 'number') {
                return;
            }

            input.style.border = input.value && parseFloat(input.value) >= 0
                ? '2px solid #4caf50'
                : '2px solid #ccc';
        });
    }
});

const form = document.getElementById('umsatzForm');
const overlay = document.getElementById('overlay');

function mindestensEinUmsatz() {
    const totals = calculator.getTotals();

    if (totals.income <= 0) {
        alert('Bitte gib entweder einen Umsatz *mit* oder *ohne* Taxameter ein.');
        return false;
    }

    return true;
}

form.addEventListener('submit', function (event) {
    event.preventDefault();

    if (!mindestensEinUmsatz()) {
        return;
    }

    overlay.style.display = 'flex';
    setTimeout(() => form.submit(), 2000);
});
</script>

</body>
</html>
