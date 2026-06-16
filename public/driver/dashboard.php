<?php
// public/driver/dashboard.php
require_once '../../includes/head.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/zeitraum_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$fahrer_id = $_SESSION['user_id'];

// Fahrer laden
$stmt = $pdo->prepare("SELECT * FROM Fahrer WHERE FahrerID = ?");
$stmt->execute([$fahrer_id]);
$fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fahrer) {
    $fahrer = ['Vorname' => 'Unbekannter', 'Nachname' => 'Benutzer'];
}

$pdo->exec("ALTER TABLE Fahrer ADD COLUMN IF NOT EXISTS shift_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0");
$pdo->exec("CREATE TABLE IF NOT EXISTS driver_shift_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    shift_date DATE NOT NULL,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    duration_minutes INT NOT NULL DEFAULT 0,
    taxidaten_shift_id VARCHAR(100) NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'taxidaten',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_driver_shift (driver_id, shift_date, taxidaten_shift_id),
    KEY idx_driver_date (driver_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ────────────────────────────────────────────────────────────
// P-Schein-Hinweis
// ────────────────────────────────────────────────────────────
$pscheinHinweis = '';
$pscheinGueltigkeit = $fahrer['PScheinGueltigkeit'] ?? null;

if ($pscheinGueltigkeit) {
    $ts = strtotime($pscheinGueltigkeit);
    $tsWarn = strtotime('+3 months');
    $tage = ceil(($ts - time()) / 86400);
    $format = date('d.m.Y', $ts);

    if ($ts <= $tsWarn) {
        if ($tage <= 0) {
            $pscheinHinweis = "
                <div class='pschein-warnung'>
                    <i class='fa-solid fa-circle-exclamation warn-icon'></i>
                    <strong>Achtung:</strong> Dein P-Schein ist bereits abgelaufen (gültig bis: $format)!
                </div>";
        } else {
            $pscheinHinweis = "
                <div class='pschein-warnung'>
                    <i class='fa-solid fa-circle-exclamation warn-icon'></i>
                    <strong>Achtung:</strong> Dein P-Schein läuft in $tage Tagen ab (gültig bis: $format)!
                </div>";
        }
    }
}

// ────────────────────────────────────────────────────────────
// Zeitraum für Tabelle
// ────────────────────────────────────────────────────────────
$zeitraum = $_GET['zeitraum'] ?? 'monat';
$periodError = null;

try {
    $period = berechneZeitraum($zeitraum, [
        'start_date' => $_GET['start_date'] ?? null,
        'end_date'   => $_GET['end_date'] ?? null,
    ]);
} catch (InvalidArgumentException $e) {
    $periodError = $e->getMessage();
    $period = berechneZeitraum('monat');
}

$start_date = $period['start_date'];
$end_date   = $period['end_date'];

// Umsätze laden
$stmt = $pdo->prepare("
    SELECT
        Datum,
        (TaxameterUmsatz + OhneTaxameter) AS Gesamtumsatz,
        (TaxameterUmsatz + OhneTaxameter
         - Kartenzahlung - Rechnungsfahrten - Krankenfahrten
         - Gutscheine - Alita - TankenWaschen - SonstigeAusgaben) AS Bargeld,
        Abgerechnet
    FROM Umsatz
    WHERE FahrerID = ?
      AND Datum BETWEEN ? AND ?
    ORDER BY Datum ASC
");
$stmt->execute([$fahrer_id, $start_date, $end_date]);
$umsatzDaten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregat
$aggregatStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(TaxameterUmsatz + OhneTaxameter), 0) AS Gesamtumsatz,
        COALESCE(SUM(TaxameterUmsatz + OhneTaxameter
         - Kartenzahlung - Rechnungsfahrten - Krankenfahrten
         - Gutscheine - Alita - TankenWaschen - SonstigeAusgaben), 0) AS Bargeld
    FROM Umsatz
    WHERE FahrerID = ?
      AND Datum BETWEEN ? AND ?
");
$aggregatStmt->execute([$fahrer_id, $start_date, $end_date]);
$agg = $aggregatStmt->fetch(PDO::FETCH_ASSOC);

$gesamtUmsatz  = (float) $agg['Gesamtumsatz'];
$gesamtBargeld = (float) $agg['Bargeld'];

$shiftMinutesPeriod = 0;
$shiftMinutesMonth = 0;
if ((int)($fahrer['shift_tracking_enabled'] ?? 0) === 1) {
    $shiftPeriodStmt = $pdo->prepare("SELECT COALESCE(SUM(duration_minutes),0) FROM driver_shift_logs WHERE driver_id = ? AND shift_date BETWEEN ? AND ?");
    $shiftPeriodStmt->execute([$fahrer_id, $start_date, $end_date]);
    $shiftMinutesPeriod = (int)$shiftPeriodStmt->fetchColumn();
}

// ────────────────────────────────────────────────────────────
// Monatsauswertung (immer aktueller Monat)
// ────────────────────────────────────────────────────────────
$monat = berechneZeitraum('monat');
$monatStart = $monat['start_date'];
$monatEnde  = $monat['end_date'];

$monatStmt = $pdo->prepare("
    SELECT COALESCE(SUM(TaxameterUmsatz + OhneTaxameter),0) AS Gesamtumsatz
    FROM Umsatz
    WHERE FahrerID = ?
      AND Datum BETWEEN ? AND ?
");
$monatStmt->execute([$fahrer_id, $monatStart, $monatEnde]);
$monatUmsatz = (float) ($monatStmt->fetchColumn() ?: 0);

if ((int)($fahrer['shift_tracking_enabled'] ?? 0) === 1) {
    $shiftMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(duration_minutes),0) FROM driver_shift_logs WHERE driver_id = ? AND shift_date BETWEEN ? AND ?");
    $shiftMonthStmt->execute([$fahrer_id, $monatStart, $monatEnde]);
    $shiftMinutesMonth = (int)$shiftMonthStmt->fetchColumn();
}

$formatHours = static function(int $minutes): string {
    $h = intdiv(max(0, $minutes), 60);
    $m = max(0, $minutes) % 60;
    return sprintf('%d:%02d h', $h, $m);
};

// Monatsziel aus Fahrerprofil
$monatsZiel = isset($fahrer['standard_monatsziel']) ? (float)$fahrer['standard_monatsziel'] : 0;

$monatsProzent = null;
if ($monatsZiel > 0) {
    $monatsProzent = min(1000, ($monatUmsatz / $monatsZiel) * 100);
}

// Tages-/Schichtziel für Badges (NEU, bisher gefehlt)
$tagesZiel = isset($fahrer['standard_schichtziel']) ? (float)$fahrer['standard_schichtziel'] : 0;

// ────────────────────────────────────────────────────────────
// Abrechnungstermin
// ────────────────────────────────────────────────────────────
$stmtAbrechnung = $pdo->query("
    SELECT Datum, Uhrzeit
    FROM Abrechnungsplanung
    WHERE Datum >= CURDATE()
    ORDER BY Datum ASC
    LIMIT 1
");
$naechsteAbrechnung = $stmtAbrechnung->fetch(PDO::FETCH_ASSOC);

// Fahrerhinweis
$stmtHinweis = $pdo->query("
    SELECT *
    FROM fahrer_mitteilungen
    WHERE sichtbar = TRUE
      AND gueltig_bis >= CURDATE()
    ORDER BY erstellt_am DESC
    LIMIT 1
");
$fahrerHinweis = $stmtHinweis->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Fahrer</title>

    <link rel="stylesheet" href="css/driver-dashboard.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/form-feedback.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'bottom_nav.php'; ?>

<main>
    <h1>Willkommen, <?= htmlspecialchars($fahrer['Vorname']) ?></h1>

    <?= $pscheinHinweis ?>

    <?php if ($naechsteAbrechnung): ?>
        <div class="hinweis-box">
            <div class="hinweis-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="hinweis-text">
                <strong>Info:</strong><br>
                Yannik plant am <strong><?= date('d.m.Y', strtotime($naechsteAbrechnung['Datum'])) ?></strong><br>
                gegen <strong><?= htmlspecialchars($naechsteAbrechnung['Uhrzeit']) ?> Uhr</strong> zur Abrechnung zu kommen.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($fahrerHinweis): ?>
        <div class="hinweis-box <?= $fahrerHinweis['wichtig'] ? 'hinweis-box--important' : 'hinweis-box--notice' ?>">
            <div class="hinweis-icon"><i class="fas fa-bullhorn"></i></div>
            <div class="hinweis-text">
                <strong>Chrissi informiert:</strong><br>
                <?= nl2br(htmlspecialchars($fahrerHinweis['nachricht'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <p>Hier findest du eine Übersicht deiner Umsätze.</p>

    <!-- ────────────────────────────────────────────────────────────
         MONATSAUSWERTUNG
         ──────────────────────────────────────────────────────────── -->
    <section class="monatsübersicht">
        <h2>Monatsübersicht</h2>

        <div class="gesamt-container">
            <div class="card umsatz">
                <span class="icon">📅</span>
                <div class="card-title">Umsatz im aktuellen Monat</div>
                <div class="card-value"><?= number_format($monatUmsatz,2,',','.') ?> €</div>
            </div>

            <?php if ((int)($fahrer['shift_tracking_enabled'] ?? 0) === 1): ?>
            <div class="card bargeld">
                <span class="icon">⏱️</span>
                <div class="card-title">Arbeitszeit <small style="font-size:.8em; opacity:.75;">ohne Pause</small> im aktuellen Monat</div>
                <div class="card-value"><?= htmlspecialchars($formatHours($shiftMinutesMonth)) ?></div>
            </div>
            <?php endif; ?>

            <div class="card ziel">
                <span class="icon">🎯</span>
                <div class="card-title">Monatsziel</div>

                <?php if ($monatsZiel > 0): ?>
                    <div class="card-value"><?= number_format($monatsZiel,2,',','.') ?> €</div>

                    <div class="progress-wrapper">
                        <div class="progress-bar">
                            <div class="progress-bar-fill"
                                 style="width: <?= min(100, max(0, round($monatsProzent))) ?>%;">
                            </div>
                        </div>
                        <div class="progress-label">
                            <?= number_format($monatsProzent,1,',','.') ?> % deines Ziels erreicht
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card-value card-value--muted">Kein Ziel gesetzt</div>
                    <div class="progress-label">
                        Du kannst dein persönliches Ziel unter
                        <a href="personal.php">„Persönliche Daten“</a> eintragen.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ────────────────────────────────────────────────────────────
         ZEITRAUM ÜBERSICHT
         ──────────────────────────────────────────────────────────── -->
    <section>
        <h2>Umsatzübersicht</h2>

        <?php if ($periodError): ?>
            <div class="form-feedback form-feedback--error period-error">
                <?= htmlspecialchars($periodError) ?> – Es wird der aktuelle Monat angezeigt.
            </div>
        <?php endif; ?>

        <form method="GET" action="dashboard.php">
            <label for="zeitraum">Zeitraum:</label>
            <select id="zeitraum" name="zeitraum"
                    onchange="toggleIndividuellFields(); this.form.submit();">
                <option value="monat"          <?= $zeitraum==='monat' ? 'selected':'' ?>>Aktueller Monat</option>
                <option value="letzte_woche"   <?= $zeitraum==='letzte_woche' ? 'selected':'' ?>>Letzte Woche</option>
                <option value="woche"          <?= $zeitraum==='woche' ? 'selected':'' ?>>Aktuelle Woche</option>
                <option value="tag"            <?= $zeitraum==='tag' ? 'selected':'' ?>>Heute</option>
                <option value="individuell"    <?= $zeitraum==='individuell' ? 'selected':'' ?>>Individueller Zeitraum</option>
            </select>

            <?php $showIndividuell = $zeitraum === 'individuell'; ?>
            <div id="individuell-fields" class="individuell-fields <?= $showIndividuell ? 'is-visible' : '' ?>">
                <label for="start_date">Startdatum:</label>
                <input type="date" id="start_date" name="start_date"
                       value="<?= htmlspecialchars($start_date) ?>">

                <label for="end_date">Enddatum:</label>
                <input type="date" id="end_date" name="end_date"
                       value="<?= htmlspecialchars($end_date) ?>">

                <button type="submit">Anzeigen</button>
            </div>
        </form>

        <!-- Zeitraum-Karten -->
        <div class="gesamt-container">
            <div class="card umsatz">
                <span class="icon">📈</span>
                <div class="card-title">Gesamtumsatz (Zeitraum)</div>
                <div class="card-value"><?= number_format($gesamtUmsatz,2,',','.') ?> €</div>
            </div>

            <div class="card bargeld">
                <span class="icon">💸</span>
                <div class="card-title">Bargeld (Zeitraum)</div>
                <div class="card-value"><?= number_format($gesamtBargeld,2,',','.') ?> €</div>
            </div>

            <?php if ((int)($fahrer['shift_tracking_enabled'] ?? 0) === 1): ?>
            <div class="card ziel">
                <span class="icon">🕒</span>
                <div class="card-title">Arbeitszeit <small style="font-size:.8em; opacity:.75;">ohne Pause</small> (Zeitraum)</div>
                <div class="card-value"><?= htmlspecialchars($formatHours($shiftMinutesPeriod)) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tagesübersicht als Cards -->
        <div class="umsatz-card-list">
            <?php if ($umsatzDaten): ?>
                <?php foreach ($umsatzDaten as $eintrag): ?>
                    <?php
                        $datumObj = DateTime::createFromFormat('Y-m-d', $eintrag['Datum']);
                        $datumDisplay = $datumObj ? $datumObj->format('d.m.Y') : $eintrag['Datum'];

                        // Badge-Berechnung pro Tag
                        $zielBadge = '';
                        $zielTitle = '';

                        if ($tagesZiel > 0 && $eintrag['Gesamtumsatz'] !== null) {
                            $tagesProzent = ($eintrag['Gesamtumsatz'] / $tagesZiel) * 100;

                            if ($tagesProzent >= 120) {
                                // Diamant: Ziel deutlich übertroffen
                                $zielBadge = 'diamond';
                                $zielTitle = number_format($tagesProzent, 1, ',', '.') . '% deines Schichtziels – überragend!';
                            } elseif ($tagesProzent >= 100) {
                                // Krone: Ziel erreicht
                                $zielBadge = 'crown';
                                $zielTitle = number_format($tagesProzent, 1, ',', '.') . '% deines Schichtziels erreicht';
                            }
                        }
                    ?>
                    <article class="umsatz-card">
						<div class="umsatz-card-header">
							<div class="umsatz-card-date">
								<?= $datumDisplay ?>
							</div>

							<?php if ($zielBadge): ?>
								<div class="umsatz-card-badge umsatz-card-badge--<?= $zielBadge ?>"
									 title="<?= htmlspecialchars($zielTitle) ?>">
									<?php if ($zielBadge === 'diamond'): ?>
										<i class="fa-solid fa-gem"></i>
									<?php else: ?>
										<i class="fa-solid fa-crown"></i>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>

                        <div class="umsatz-card-body">
                            <div class="umsatz-card-row umsatz-card-row--highlight">
                                <span class="umsatz-card-label">Gesamtumsatz</span>
                                <span class="umsatz-card-value">
                                    <?= number_format($eintrag['Gesamtumsatz'],2,',','.') ?> €
                                </span>
                            </div>
                            <div class="umsatz-card-row">
                                <span class="umsatz-card-label">Bargeld</span>
                                <span class="umsatz-card-value">
                                    <?= number_format($eintrag['Bargeld'],2,',','.') ?> €
                                </span>
                            </div>
                        </div>

                        <footer class="umsatz-card-actions">
                            <?php if ((int)$eintrag['Abgerechnet'] === 0): ?>
                                <a class="btn-plain btn-edit"
                                   href="update_entry.php?datum=<?= urlencode($eintrag['Datum']) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                    <span>Bearbeiten</span>
                                </a>
                                <a class="btn-plain btn-delete"
                                   onclick="return confirm('Diesen Eintrag wirklich löschen?');"
                                   href="delete_entry.php?datum=<?= urlencode($eintrag['Datum']) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                    <span>Löschen</span>
                                </a>
                            <?php else: ?>
                                <span class="abgerechnet-hinweis">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Abgerechnet
                                </span>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Keine Umsätze für den gewählten Zeitraum.</p>
            <?php endif; ?>
        </div>

        <a href="statistics.php">Statistik</a>
    </section>
</main>

<script>
function toggleIndividuellFields() {
    const z = document.getElementById('zeitraum').value;
    const fields = document.getElementById('individuell-fields');
    if (!fields) return;
    fields.classList.toggle('is-visible', z === 'individuell');
}
document.addEventListener('DOMContentLoaded', toggleIndividuellFields);
</script>

</body>
</html>
