<?php
require_once '../../includes/head.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'fahrer') {
    header("Location: ../index.php");
    exit();
}

$error = '';
$results = [];
$requestUrl = null;

// 1) Fahrernummer (Displaynummer) ermitteln
$fahrer_id = $_SESSION['user_id'];
$fahrer_displaynummer = null;

try {
    $stmt = $pdo->prepare("
        SELECT 
            NULLIF(TRIM(Fahrernummer), '') AS fahrernummer,
            NULLIF(TRIM(fms_alias), '')    AS fms_alias
        FROM Fahrer 
        WHERE FahrerID = :id 
        LIMIT 1
    ");
    $stmt->execute(['id' => $fahrer_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Fahrer nicht gefunden.');
    }
    $fahrer_displaynummer = $row['fahrernummer'] ?? $row['fms_alias'] ?? null;

    if (!$fahrer_displaynummer) {
        throw new Exception('Keine Fahrernummer/FMS-Alias für diesen Fahrer hinterlegt.');
    }
} catch (Exception $e) {
    $error = 'Fehler bei Fahrer-Ermittlung: ' . $e->getMessage();
}

// 2) Datum-Defaults (heute)
$heute = new DateTime('today');
$defaultVon = $heute->format('Y-m-d');
$defaultBis = $heute->format('Y-m-d');

// 3) Eingaben lesen
$von = isset($_GET['von']) ? $_GET['von'] : $defaultVon;
$bis = isset($_GET['bis']) ? $_GET['bis'] : $defaultBis;

// 4) Wenn Such-Request: API aufrufen
if (isset($_GET['action']) && $_GET['action'] === 'search' && !$error) {
    try {
        $dtVon = DateTime::createFromFormat('Y-m-d H:i', $von . ' 00:00');
        $dtBis = DateTime::createFromFormat('Y-m-d H:i', $bis . ' 23:59');

        if (!$dtVon || !$dtBis) throw new Exception('Ungültiges Datum.');
        if ($dtVon > $dtBis)    throw new Exception('Startdatum darf nicht nach dem Enddatum liegen.');

        // API erwartet dd.mm.YYYY HH:MM
        $apiVon = $dtVon->format('d.m.Y H:i');
        $apiBis = $dtBis->format('d.m.Y H:i');

        $base = 'https://4884gateway.de/fms';
        $query = [
            'funktion'             => 'GETAUFTRAGLISTE',
            'DATUM_VON'            => $apiVon,
            'FAHRER_DISPLAYNUMMER' => $fahrer_displaynummer,
            'AUFTRAGSTATUS'        => 7,
            'DATUM_BIS'            => $apiBis
        ];
        $requestUrl = $base . '?' . str_replace('+', '%20', http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: DRIVE-Fahrer-Portal/1.0'
            ],
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false)                 throw new Exception('Netzwerkfehler: ' . $curlErr);
        if ($status < 200 || $status >= 300)     throw new Exception('HTTP-Status ' . $status . ' vom Gateway.');

        $decoded = json_decode($response, true);
        if (!is_array($decoded))                 throw new Exception('Unerwartetes Antwortformat (kein gültiges JSON-Array).');

        $results = $decoded;
    } catch (Exception $e) {
        $error = 'Suche fehlgeschlagen: ' . $e->getMessage();
    }
}

// DateTime-Parser für "d.m.Y H:i[:s]" → abgeschnitten auf Minute
function parseGatewayDtToMinute(?string $s): ?DateTime {
    if (!$s) return null;
    $dt = DateTime::createFromFormat('d.m.Y H:i:s', $s) ?: DateTime::createFromFormat('d.m.Y H:i', $s);
    if (!$dt) return null;
    $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);
    return $dt;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auftragsrecherche | DRIVE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bestehendes CSS -->
    <link rel="stylesheet" href="css/driver-dashboard.css">

    <!-- Bootstrap 5 CSS -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    >
    <!-- Bootstrap Icons -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
      integrity="sha384-e0tpslWfZ2k7fO2ZJ0oQo6C9m5ZgF0i5hUP3pC3Z7hYtUeTtnB1uF0v2R7R0Z9bN"
      crossorigin="anonymous"
    />

    <style>
        :root {
            --c-primary: #0d6efd;
            --c-muted: #6c757d;
            --c-bg: #f6f8fb;
            --card-bg: #fff;
            --ok: #388e3c;
            --label-w: 13ch; /* fixe Labelspalte */
        }
        @media (min-width: 576px) { :root { --label-w: 13ch; } }
        @media (min-width: 992px) { :root { --label-w: 14ch; } }

        body { margin: 0; background: var(--c-bg); color: #222; }
        main { max-width: 1100px; margin: 20px auto; padding: 0 16px 80px; }
        .page-title { margin: 12px 0 16px; font-size: 1.6rem; }

        /* Suchleiste */
        .searchbar {
            background: var(--card-bg);
            border: 1px solid #e7eaf0;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .quick-filters { margin-top: .25rem; }
        .quick-filters .label { color: var(--c-muted); font-size: .85rem; }
        .quick-filters .btn { padding: 2px 8px; font-weight: 600; }

        /* Karten & Typografie */
        .result-card { text-align: left; }
        .card-title { font-weight: 700; letter-spacing: .2px; }
        .k {
            text-transform: uppercase; letter-spacing: .6px;
            font-size: .78rem; color: #6c757d; line-height: 1.2; white-space: nowrap;
        }

        /* Key/Value-Grid */
        .kv {
            display: grid; grid-template-columns: var(--label-w) 1fr;
            gap: 6px 12px; font-size: .95rem; align-items: start;
        }
        .kv div, .time-pill { font-variant-numeric: tabular-nums; }
        .kv > div + div > div { line-height: 1.25; }
        .indent { margin-left: var(--label-w); }

        /* Badges */
        .badge-pill { border-radius: 999px; font-weight: 700; padding: 2px 10px; font-size: .78rem; }
        .badge-einsteiger { background: #e7f3ff; color: #0b5ed7; border: 1px solid #cfe2ff; }
        .badge-sofort     { background: #eaf7ea; color: #2e7d32; border: 1px solid #cfe8cf; }
        .badge-vorbest    { background: #fff7e6; color: #b76e00; border: 1px solid #ffe4b5; }
        .badge-kkb        { background: #fdecea; color: #b02a37; border: 1px solid #f5c2c7; }

        /* Kanten-Akzente */
        .card.kkb        { border-left: 4px solid #dc3545; }
        .card.einsteiger { border-left: 4px solid #0b5ed7; }

        /* Zeit-Pill */
        .time-pill {
          display: inline-flex; align-items: center;
          padding: 2px 8px; border-radius: 999px;
          background: #f1f3f5; color: #495057; font-weight: 600; font-size: .8rem;
          border: 1px solid #e9ecef;
        }

        /* Hairline */
        .hr-hairline {
          height: 1px; background: linear-gradient(90deg, transparent, #e9ecef, transparent);
          border: 0; margin: .5rem 0 1rem;
        }

        /* Hover-Lift */
        @media (prefers-reduced-motion: no-preference) {
          .result-card { transition: transform .08s ease, box-shadow .08s ease; }
          .result-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
          }
        }

        /* Chips */
        .chip {
          display:inline-flex; align-items:center; gap:.35rem;
          padding: 2px 8px; border-radius: 999px; font-weight: 600; font-size: .75rem;
          background: #f8f9fa; color: #6c757d; border: 1px solid #e9ecef;
        }

        /* Empty-State */
        .empty { padding: 16px; border: 1px dashed #cfd6e4; border-radius: 12px; background: #fff; }

        /* Overlay / Loader */
        #overlay {
            position: fixed; inset: 0; background: rgba(255,255,255,.85);
            display: none; align-items: center; justify-content: center; z-index: 9999; flex-direction: column;
        }
        .loader {
            width: 56px; height: 56px; border: 4px solid #dfe7f5; border-top-color: var(--c-primary);
            border-radius: 50%; animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loader-text { margin-top: 10px; color:#333; font-weight:600; }
    </style>
</head>
<body class="bg-light">
<?php include 'bottom_nav.php'; ?>
<main>
    <h1 class="page-title">Auftragsrecherche</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Suchformular -->
    <form id="searchForm" class="searchbar mb-3" method="get" action="recherche.php">
        <div class="row g-3 align-items-end text-start">
            <div class="col-12 col-sm-6 col-md-4">
                <label for="von" class="form-label">Von</label>
                <input type="date" id="von" name="von" value="<?= htmlspecialchars($von) ?>" required class="form-control">
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="bis" class="form-label">Bis</label>
                <input type="date" id="bis" name="bis" value="<?= htmlspecialchars($bis) ?>" required class="form-control">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2 justify-content-md-end">
                <input type="hidden" name="action" value="search">
                <button class="btn btn-primary fw-semibold" type="submit" <?= $fahrer_displaynummer ? '' : 'disabled' ?>>Suchen</button>
            </div>
        </div>

        <!-- dezenter Schnellfilter -->
        <div class="quick-filters d-flex align-items-center gap-2">
            <button type="button" id="btnGestern" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-calendar2-minus me-1"></i>Gestern
            </button>
        </div>
    </form>

    <!-- Ergebnisse -->
    <?php if ($results && is_array($results)): ?>
      <div class="row g-3">
        <?php foreach ($results as $i => $row):
            $datum  = $row['datumDerFahrt']         ?? '';
            $vermit = $row['vermittlungszeitpunkt'] ?? '';
            $ab     = $row['abfahrt']               ?? [];
            $ziel   = $row['ziel']                  ?? [];

            $ab_name = $ab['kundeName'] ?? ($ab['fahrtkundeName'] ?? '');
            $ab_str  = trim(($ab['strasse'] ?? '') . ' ' . ($ab['hausnummer'] ?? ''));
            $ab_ort  = $ab['ort'] ?? '';
            $zi_name = $ziel['kundeName'] ?? ($ziel['fahrtkundeName'] ?? '');
            $zi_str  = trim(($ziel['strasse'] ?? '') . ' ' . ($ziel['hausnummer'] ?? ''));
            $zi_ort  = $ziel['ort'] ?? '';

            // Merkmale parsen
            $merkmaleRaw = $row['merkmale'] ?? [];
            if (is_string($merkmaleRaw)) {
                $merkmale = preg_split('/\s*,\s*|\s+/', trim($merkmaleRaw)) ?: [];
            } elseif (is_array($merkmaleRaw)) {
                $merkmale = $merkmaleRaw;
            } else {
                $merkmale = [];
            }
            $merkmaleUpper = array_map(static function($m){ return strtoupper(trim((string)$m)); }, $merkmale);
            $hasKkb = in_array('KKB', $merkmaleUpper, true);

            // Einsteiger-/Auftragsart
            $isEinsteiger = false;
            $abKundeNameRaw = $ab['kundeName'] ?? ($ab['fahrtkundeName'] ?? '');
            if (is_string($abKundeNameRaw) && strtoupper(trim($abKundeNameRaw)) === 'GPS') {
                $isEinsteiger = true;
            }

            $dtFahrtMin = parseGatewayDtToMinute($row['datumDerFahrt'] ?? null);
            $dtVermMin  = parseGatewayDtToMinute($row['vermittlungszeitpunkt'] ?? null);

            // Badges: Einsteiger > Sofort/Vorbestellung ; KKB zusätzlich
            $badges = [];
            if ($isEinsteiger) {
                $badges[] = '<span class="badge-pill badge-einsteiger" data-bs-toggle="tooltip" title="Kunde GPS in Abfahrt → Einsteiger">Einsteiger</span>';
            }
            if ($dtFahrtMin && $dtVermMin) {
                if ($dtFahrtMin == $dtVermMin) {
                    if (!$isEinsteiger) {
                        $badges[] = '<span class="badge-pill badge-sofort" data-bs-toggle="tooltip" title="vermittelt = sofort gefahren">Sofortauftrag</span>';
                    }
                } else {
                    $badges[] = '<span class="badge-pill badge-vorbest" data-bs-toggle="tooltip" title="Vermittelt vor Abfahrt">Vorbestellung</span>';
                }
            }
            if ($hasKkb) {
                $badges[] = '<span class="badge-pill badge-kkb" data-bs-toggle="tooltip" title="Merkmal: KKB">KKB</span>';
            }
            $badgesInlineHtml = $badges ? '<span class="ms-2 d-inline-flex flex-wrap gap-2 align-items-center">'.implode('', $badges).'</span>' : '';

            $cardClasses = 'card shadow-sm h-100 result-card'
                         . ($isEinsteiger ? ' einsteiger' : '')
                         . ($hasKkb ? ' kkb' : '');

            // Datum/Zeit fürs Titelband
            $fahrtDatum = $dtFahrtMin ? $dtFahrtMin->format('d.m.Y') : (preg_match('/^\d{2}\.\d{2}\.\d{4}/', $datum, $m) ? $m[0] : htmlspecialchars($datum));
            $fahrtZeit  = $dtFahrtMin ? $dtFahrtMin->format('H:i')   : (preg_match('/\b(\d{2}:\d{2})\b/', $datum, $m) ? $m[1] : '');
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="<?= $cardClasses ?>">
            <div class="card-body text-start">

              <!-- Titel: Datum + Zeit-Pill + Badges -->
              <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h5 class="card-title m-0"><?= htmlspecialchars($fahrtDatum) ?></h5>
                <?php if ($fahrtZeit): ?>
                  <span class="time-pill"><i class="bi bi-clock"></i><?= htmlspecialchars($fahrtZeit) ?></span>
                <?php endif; ?>
                <?= $badgesInlineHtml ?>
              </div>

              <div class="hr-hairline"></div>

              <!-- Vermittlung -->
              <div class="kv mb-2">
                <div class="k"><i class="bi bi-broadcast-pin me-1"></i>Vermittelt</div>
                <div><?= htmlspecialchars($vermit) ?></div>
              </div>

              <!-- Abfahrt -->
              <div class="kv mb-1">
                <div class="k"><i class="bi bi-flag-fill me-1"></i>Abfahrt</div>
                <div>
                  <?php if ($ab_name): ?><div><strong><?= htmlspecialchars($ab_name) ?></strong></div><?php endif; ?>
                  <?php if ($ab_str):  ?><div><?= htmlspecialchars($ab_str) ?></div><?php endif; ?>
                  <?php if ($ab_ort):  ?><div class="text-muted"><?= htmlspecialchars($ab_ort) ?></div><?php endif; ?>
                </div>
              </div>

              <!-- Ziel -->
              <div class="kv">
                <div class="k"><i class="bi bi-geo-alt me-1"></i>Ziel</div>
                <div>
                  <?php if ($zi_name): ?><div><strong><?= htmlspecialchars($zi_name) ?></strong></div><?php endif; ?>
                  <?php if ($zi_str):  ?><div><?= htmlspecialchars($zi_str) ?></div><?php endif; ?>
                  <?php if ($zi_ort):  ?><div class="text-muted"><?= htmlspecialchars($zi_ort) ?></div><?php endif; ?>
                </div>
              </div>

              <?php
                // übrige Merkmale als Chips (ohne KKB)
                $otherChips = [];
                foreach ($merkmaleUpper as $m) {
                    if ($m === 'KKB' || $m === '') continue;
                    $otherChips[] = '<span class="chip" title="Merkmal">'.htmlspecialchars($m).'</span>';
                }
                if ($otherChips) {
                    echo '<div class="mt-2 d-flex flex-wrap gap-2 indent">'.implode('', $otherChips).'</div>';
                }
              ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (isset($_GET['action']) && $_GET['action'] === 'search' && !$error): ?>
      <div class="empty text-start">Keine Fahrten im gewählten Zeitraum gefunden.</div>
    <?php endif; ?>
</main>

<?php include 'nav-script.php'; ?>

<!-- Overlay (Warteanimation) -->
<div id="overlay" aria-hidden="true">
  <div class="loader"></div>
  <div class="loader-text" role="status" aria-live="polite">Suche läuft...</div>
</div>

<!-- Bootstrap 5 JS -->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
  crossorigin="anonymous"
></script>
<script>
// Tooltips
document.addEventListener('DOMContentLoaded', () => {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
});

// Overlay-Handling + "Gestern"-Schnellfilter
const form = document.getElementById('searchForm');
const overlay = document.getElementById('overlay');
const von = document.getElementById('von');
const bis = document.getElementById('bis');
const btnGestern = document.getElementById('btnGestern');

function toYMD(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const day = String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${day}`;
}

if (btnGestern) {
  btnGestern.addEventListener('click', () => {
    const d = new Date();
    d.setDate(d.getDate() - 1); // gestern (Client-Zeit)
    const ymd = toYMD(d);
    von.value = ymd;
    bis.value = ymd;
    overlay.style.display = 'flex';
    if (form.requestSubmit) form.requestSubmit();
    else form.submit();
  });
}

form.addEventListener('submit', function(e) {
    if (!von.value || !bis.value) return;
    const dtVon = new Date(von.value + 'T00:00:00');
    const dtBis = new Date(bis.value + 'T23:59:00');
    if (dtVon > dtBis) {
        e.preventDefault();
        alert('Startdatum darf nicht nach dem Enddatum liegen.');
        return;
    }
    overlay.style.display = 'flex';
});
</script>
</body>
</html>
