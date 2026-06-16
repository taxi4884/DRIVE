<?php
require_once '../../includes/head.php'; // Stellt $pdo und Session bereit
require_once __DIR__ . '/error_handler.php';

function formatGermanDateOrOriginal(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y');
    } catch (Exception $e) {
        return (string) $value;
    }
}

// ==== Session / Rolle prüfen (optional identisch zu umsatz_erfassen.php) ====
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// Falls du wie in umsatz_erfassen.php hart auf Rolle prüfen willst, entkommentieren:
// if (($_SESSION['user_role'] ?? null) !== 'fahrer') {
//     header('Location: ../index.php');
//     exit();
// }

$fahrer_id = (int)$_SESSION['user_id'];

// ==== Eingabe prüfen ====
if (!isset($_GET['datum']) || empty($_GET['datum'])) {
    throw new InvalidArgumentException('Eintrag nicht gefunden: kein Datum übergeben.');
}

// Wir erwarten ein ISO-Datum (YYYY-MM-DD) – in der DB kann Datum DATETIME sein
$datum_param = $_GET['datum'];
$datumAnzeige = formatGermanDateOrOriginal($datum_param);

$error = '';
$success = '';

// ==== Eintrag laden (robust gegen DATETIME in der DB) ====
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM Umsatz WHERE FahrerID = :fahrer_id AND DATE(Datum) = :datum LIMIT 1"
    );
    $stmt->execute([
        ':fahrer_id' => $fahrer_id,
        ':datum'     => $datum_param,
    ]);
    $eintrag = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eintrag) {
        throw new RuntimeException(sprintf('Eintrag für Fahrer %d am %s nicht gefunden.', $fahrer_id, $datum_param));
    }
} catch (Exception $e) {
    throw new RuntimeException('Fehler beim Laden des Eintrags.', 0, $e);
}

// ==== Recherche-Flag wie in umsatz_erfassen.php laden (optional UI-Link) ====
$recherche_flag = 0;
try {
    $stmtRecherche = $pdo->prepare(
        "SELECT COALESCE(recherche, 0) AS recherche FROM Fahrer WHERE FahrerID = :fahrer_id LIMIT 1"
    );
    $stmtRecherche->execute([':fahrer_id' => $fahrer_id]);
    $recherche_flag = (int)$stmtRecherche->fetchColumn();
} catch (Exception $e) {
    $recherche_flag = 0; // UI soll nicht brechen
}

// ==== Update-Handling ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Zahlenfelder defensiv parsen, NULL -> 0
    $taxameter_umsatz  = (float)($_POST['taxameter_umsatz']  ?? 0);
    $ohne_taxameter    = (float)($_POST['ohne_taxameter']    ?? 0);
    $kartenzahlung     = (float)($_POST['kartenzahlung']     ?? 0);
    $rechnungsfahrten  = (float)($_POST['rechnungsfahrten']  ?? 0);
    $krankenfahrten    = (float)($_POST['krankenfahrten']    ?? 0);
    $gutscheine        = (float)($_POST['gutscheine']        ?? 0);
    $alita             = (float)($_POST['alita']             ?? 0);
    $tanken_waschen    = (float)($_POST['tanken_waschen']    ?? 0);
    $sonstige_ausgaben = (float)($_POST['sonstige_ausgaben'] ?? 0);
    $notiz             = trim((string)($_POST['notiz']        ?? ''));

    try {
        // Optional: Geschäftslogik wie in umsatz_erfassen.php – mind. ein Umsatz
        if ($taxameter_umsatz <= 0 && $ohne_taxameter <= 0) {
            throw new Exception('Bitte mindestens einen Umsatz (mit oder ohne Taxameter) eingeben.');
        }

        $stmtUpd = $pdo->prepare(
            "UPDATE Umsatz
             SET TaxameterUmsatz = :taxameter,
                 OhneTaxameter   = :ohne,
                 Kartenzahlung   = :karte,
                 Rechnungsfahrten= :rechnung,
                 Krankenfahrten  = :kranken,
                 Gutscheine      = :gutscheine,
                 Alita           = :alita,
                 TankenWaschen   = :tanken,
                 SonstigeAusgaben= :sonst,
                 Notiz           = :notiz
             WHERE FahrerID = :fahrer_id AND DATE(Datum) = :datum"
        );

        $stmtUpd->execute([
            ':taxameter'  => $taxameter_umsatz,
            ':ohne'       => $ohne_taxameter,
            ':karte'      => $kartenzahlung,
            ':rechnung'   => $rechnungsfahrten,
            ':kranken'    => $krankenfahrten,
            ':gutscheine' => $gutscheine,
            ':alita'      => $alita,
            ':tanken'     => $tanken_waschen,
            ':sonst'      => $sonstige_ausgaben,
            ':notiz'      => $notiz !== '' ? $notiz : null,
            ':fahrer_id'  => $fahrer_id,
            ':datum'      => $datum_param,
        ]);

        $success = 'Eintrag erfolgreich aktualisiert!';

        // Nachladen des (ggf. geänderten) Datensatzes, damit UI neue Werte zeigt
        $stmt->execute([':fahrer_id' => $fahrer_id, ':datum' => $datum_param]);
        $eintrag = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        driver_log_exception($e);
        $error = 'Fehler beim Aktualisieren des Eintrags: ' . htmlspecialchars($e->getMessage());
    }
}

// ==== Hilfsfunktion für sichere Anzeige ====
function fval($arr, $key) {
    if (!isset($arr[$key]) || $arr[$key] === null) return '';
    // Zahlen DB->String
    if (is_numeric($arr[$key])) return (string)(float)$arr[$key];
    return (string)$arr[$key];
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Umsatz bearbeiten | DRIVE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/driver-dashboard.css">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    />
    <style>
        fieldset { border: 2px solid #ccc; border-radius: 8px; padding: 10px 15px; margin-bottom: 20px; }
        .gruen { background-color: #e8f5e9; }
        .blau  { background-color: #e3f2fd; }
        .rot   { background-color: #ffebee; }
        .gelb  { background-color: #fffde7; }
        legend { font-weight: bold; }
        form, label, legend, textarea, input { text-align: left; }
        label { display: block; margin-top: 10px; }
        input[type="number"], input[type="date"], input[type="text"], textarea { width: 100%; padding: 6px; box-sizing: border-box; }
        textarea { vertical-align: top; }
        #overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(255,255,255,0.95); z-index:9999; display:none; align-items:center; justify-content:center; flex-direction:column; }
        .taxi-wrapper { position: relative; width: 100vw; height: 80px; overflow: hidden; }
        .taxi { width:60px; height:60px; background-image:url('https://em-content.zobj.net/source/microsoft/310/taxi_1f695.png'); background-size:contain; background-repeat:no-repeat; position:absolute; top:10px; left:100%; animation: drive 2s linear infinite; }
        .puste { font-size:40px; position:absolute; top:20px; right: calc(100% + 30px); animation: blink 1s infinite, drivePuste 2s linear infinite; }
        @keyframes drive { 0%{left:100%;} 100%{left:-100px;} }
        @keyframes drivePuste { 0%{left: calc(100% + 50px);} 100%{left:-50px;} }
        @keyframes blink { 0%,100%{opacity:1; transform:scale(1);} 25%,75%{opacity:.4; transform:scale(1.2);} 50%{opacity:1; transform:scale(1);} }
        #overlay p { font-weight:bold; font-size:18px; color:#333; margin-top:20px; }
        .recherche-hinweis { margin: 8px 0 4px; }
        .recherche-link { display:inline-block; padding:6px 10px; border-radius:6px; border:1px solid #1976d2; text-decoration:none; font-weight:600; }
        .recherche-link:hover { background:#e3f2fd; }
    </style>
</head>
<body>
<?php include 'bottom_nav.php'; ?>
<main>
    <h1>Umsatz bearbeiten</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if ($recherche_flag === 1): ?>
        <p class="recherche-hinweis">
            <a href="recherche.php" class="recherche-link">Auftragsrecherche</a>
        </p>
    <?php endif; ?>

    <form id="umsatzUpdateForm" action="update_entry.php?datum=<?= htmlspecialchars($datum_param) ?>" method="POST">
        <label for="datum">Datum:</label>
        <input type="text" id="datum" value="<?= htmlspecialchars($datumAnzeige) ?>" readonly>

        <fieldset class="gruen">
            <legend>Bargeld-Einnahmen</legend>
            <label for="taxameter">Umsatz mit Taxameter (€):</label>
            <input type="number" id="taxameter" name="taxameter_umsatz" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'TaxameterUmsatz')) ?>">

            <label for="ohne_taxameter">Umsatz ohne Taxameter (€):</label>
            <input type="number" id="ohne_taxameter" name="ohne_taxameter" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'OhneTaxameter')) ?>">
        </fieldset>

        <fieldset class="blau">
            <legend>Bargeldlose Umsätze</legend>
            <label for="kartenzahlung">Kartenzahlungen (€):</label>
            <input type="number" id="kartenzahlung" name="kartenzahlung" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'Kartenzahlung')) ?>">

            <label for="rechnungsfahrten">Rechnungsfahrten (€):</label>
            <input type="number" id="rechnungsfahrten" name="rechnungsfahrten" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'Rechnungsfahrten')) ?>">

            <label for="krankenfahrten">Krankenfahrten ohne Zuzahlung (€):</label>
            <input type="number" id="krankenfahrten" name="krankenfahrten" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'Krankenfahrten')) ?>">

            <label for="gutscheine">Gutscheine (€):</label>
            <input type="number" id="gutscheine" name="gutscheine" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'Gutscheine')) ?>">

            <label for="alita">Alita (€):</label>
            <input type="number" id="alita" name="alita" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'Alita')) ?>">
        </fieldset>

        <fieldset class="rot">
            <legend>Ausgaben</legend>
            <label for="tanken_waschen">Tanken/Waschen (€):</label>
            <input type="number" id="tanken_waschen" name="tanken_waschen" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'TankenWaschen')) ?>">

            <label for="sonstige_ausgaben">Sonstige Ausgaben (€):</label>
            <input type="number" id="sonstige_ausgaben" name="sonstige_ausgaben" step="0.01" min="0" value="<?= htmlspecialchars(fval($eintrag,'SonstigeAusgaben')) ?>">
        </fieldset>

        <fieldset class="gelb">
            <legend>Übriges Bargeld</legend>
            <label for="gesamtumsatz">Bargeld (€):</label>
            <input type="text" id="gesamtumsatz" readonly>
        </fieldset>

        <label for="notiz">Notiz (optional):</label>
        <textarea id="notiz" name="notiz" rows="4" cols="50"><?= htmlspecialchars((string)($eintrag['Notiz'] ?? '')) ?></textarea>

        <button type="submit">Eintrag aktualisieren</button>
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
<script src="js/umsatz-helpers.js"></script>
<script>
(function () {
    const helpers = window.UmsatzHelpers;
    if (!helpers) {
        return;
    }

    helpers.bindNumericInputs();
    helpers.calculateTotal();
    helpers.initForm({
        formId: 'umsatzUpdateForm',
        overlayId: 'overlay',
        submitDelay: 2000
    });
})();
</script>
</body>
</html>
