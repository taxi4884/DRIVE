<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/head.php'; // Datenbankverbindung und Authentifizierung
require_once __DIR__ . '/error_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$fahrer_id = $_SESSION['user_id'];

// Fahrername abrufen
$stmtFahrer = $pdo->prepare('SELECT Vorname, Nachname FROM Fahrer WHERE FahrerID = :id');
$stmtFahrer->execute(['id' => $fahrer_id]);
$fahrer = $stmtFahrer->fetch(PDO::FETCH_ASSOC);
$fahrer_name = $fahrer ? $fahrer['Vorname'] . ' ' . $fahrer['Nachname'] : 'Unbekannter Fahrer';

$feedbackMessage = null;
$feedbackType = null;

function normalizeKilometerValue($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $normalized = str_replace(['km', 'KM', 'Km', 'kM'], '', (string) $value);
    $normalized = str_replace(["\u{00A0}", ' '], '', $normalized);
    $normalized = trim($normalized);
    if ($normalized === '') {
        return null;
    }

    $hasComma = strpos($normalized, ',') !== false;
    $hasDot = strpos($normalized, '.') !== false;

    if ($hasComma && $hasDot) {
        if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } elseif ($hasComma) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } else {
        $normalized = str_replace(',', '', $normalized);
    }

    return is_numeric($normalized) ? (float) $normalized : null;
}

function fetchLatestKilometer(PDO $pdo, int $fahrzeugId): array
{
    // Wir nehmen *nur* kilometer_history als Wahrheit:
    // Spalten: FahrzeugID, date, mileage

    $sql = "
        SELECT 
            mileage AS wert,
            `date` AS zeitpunkt
        FROM kilometer_history
        WHERE FahrzeugID = :fid
        ORDER BY `date` DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['fid' => $fahrzeugId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['wert'] === null || $row['wert'] === '') {
        return [
            'wert'  => null,
            'datum' => null,
        ];
    }

    // Einheitliche Normalisierung wie bei allen anderen KM-Werten
    $wert = normalizeKilometerValue($row['wert']);

    if ($wert === null) {
        return [
            'wert'  => null,
            'datum' => null,
        ];
    }

    return [
        'wert'  => $wert,
        'datum' => $row['zeitpunkt'] ?? null,
    ];
}

// Verarbeitung der Inspektionsmeldung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspektion_melden'])) {
    $fahrzeugID = (int) $_POST['fahrzeug_id'];
    $konzession = $_POST['konzession'] ?? '';
    $kennzeichen = $_POST['kennzeichen'] ?? '';
    $meldung = $_POST['meldung'] ?? '';
    $rest_km = $_POST['rest_km'] ?? '';
    $gesamt_km = $_POST['gesamt_km'] ?? '';

    $gesamtKmWert = normalizeKilometerValue($gesamt_km);
    $kilometerdaten = fetchLatestKilometer($pdo, $fahrzeugID);
    $letzterKilometer = $kilometerdaten['wert'];

    if ($gesamtKmWert === null) {
        $feedbackMessage = 'Bitte gib einen gültigen Gesamtkilometerstand ein.';
        $feedbackType = 'error';
    } elseif ($letzterKilometer !== null && $gesamtKmWert <= $letzterKilometer) {
        $feedbackMessage = 'Der eingegebene Gesamtkilometerstand muss größer sein als der zuletzt gespeicherte Wert (' . number_format($letzterKilometer, 0, ',', '.') . ' km).';
        $feedbackType = 'error';
    } else {
        $phpmailerPath = __DIR__ . '/../../phpmailer/';
        require $phpmailerPath . 'Exception.php';
        require $phpmailerPath . 'PHPMailer.php';
        require $phpmailerPath . 'SMTP.php';

        $mail = new PHPMailer(true);

        try {
            $mail->setFrom('no-reply@drive.4884.de', 'DRIVE System');
            $mail->addAddress('verwaltung@taxi4884.de');
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = "Inspektionsmeldung – Konzession {$konzession}";
            $mail->Body =
                "🚗 Fahrzeugmeldung zur Inspektion\n\n" .
                "🔢 Konzession: {$konzession}\n" .
                "🚘 Kennzeichen: {$kennzeichen}\n" .
                "📝 Anzeigetext: {$meldung}\n" .
                "📉 Restkilometer: {$rest_km} km\n" .
                "📈 Gesamtkilometerstand: {$gesamt_km} km\n\n" .
                "👤 Gemeldet von: {$fahrer_name} (ID: {$fahrer_id})";

            $mail->send();
            $feedbackMessage = 'Die Inspektionsmeldung wurde erfolgreich an die Verwaltung versendet.';
            $feedbackType = 'success';
        } catch (Exception $e) {
            $feedbackMessage = 'Fehler beim Senden der E-Mail: ' . htmlspecialchars($mail->ErrorInfo);
            $feedbackType = 'error';
        }
    }
}

$heute = new DateTimeImmutable('today');

// Daten für Fahrzeuge abrufen
$fahrzeugQuery = <<<'SQL'
    SELECT
        Fahrzeuge.FahrzeugID,
        Fahrzeuge.Konzessionsnummer,
        Fahrzeuge.Kennzeichen,
        Fahrzeuge.Marke,
        Fahrzeuge.Modell
    FROM Fahrzeuge
    JOIN FahrerFahrzeug ON FahrerFahrzeug.FahrzeugID = Fahrzeuge.FahrzeugID
    WHERE FahrerFahrzeug.FahrerID = ?
    ORDER BY Fahrzeuge.Konzessionsnummer ASC, Fahrzeuge.Kennzeichen ASC
SQL;

try {
    $stmt = $pdo->prepare($fahrzeugQuery);
    $stmt->execute([$fahrer_id]);
    $fahrzeuge = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    throw new RuntimeException('Datenbankfehler beim Laden der Fahrzeugdaten.', 0, $e);
}

$fahrzeugIds = array_column($fahrzeuge, 'FahrzeugID');
$wartungenProFahrzeug = [];

if (!empty($fahrzeugIds)) {
    $placeholder = implode(',', array_fill(0, count($fahrzeugIds), '?'));
    $wartungQuery = <<<SQL
        SELECT
            FahrzeugID,
            Wartungsdatum,
            Beschreibung,
            Werkstatt,
            Bemerkungen
        FROM Wartung
        WHERE FahrzeugID IN ($placeholder)
        ORDER BY Wartungsdatum ASC
SQL;

    try {
        $wartungStmt = $pdo->prepare($wartungQuery);
        $wartungStmt->execute($fahrzeugIds);
        while ($row = $wartungStmt->fetch(PDO::FETCH_ASSOC)) {
            $wartungenProFahrzeug[$row['FahrzeugID']][] = $row;
        }
    } catch (PDOException $e) {
        $wartungenProFahrzeug = [];
    }
}

$kilometerstaende = [];
foreach ($fahrzeugIds as $id) {
    $kilometerstaende[$id] = fetchLatestKilometer($pdo, (int) $id);
}

function formatDate(?string $datum): string
{
    if (!$datum) {
        return 'Keine Angabe';
    }

    try {
        return (new DateTimeImmutable($datum))->format('d.m.Y');
    } catch (Exception $e) {
        return 'Keine Angabe';
    }
}

function formatDateTime(?string $datumZeit): string
{
    if (!$datumZeit) {
        return 'Keine Angabe';
    }

    try {
        return (new DateTimeImmutable($datumZeit))->format('d.m.Y H:i');
    } catch (Exception $e) {
        return 'Keine Angabe';
    }
}

function formatKilometer(?float $wert): string
{
    if ($wert === null) {
        return 'Keine Angabe';
    }

    return number_format($wert, 0, ',', '.') . ' km';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Fahrzeuge | DRIVE</title>
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/driver-dashboard.css">
    <link rel="stylesheet" href="css/fahrzeuge.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="fahrzeug-page">
    <main>
        <h1><i class="fa-solid fa-car-side"></i> Meine Fahrzeuge</h1>

        <?php if ($feedbackMessage): ?>
            <div class="alert <?= $feedbackType === 'success' ? 'alert-success' : 'alert-error'; ?>" role="status" aria-live="polite">
                <i class="fa-solid <?= $feedbackType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                <div class="alert-content">
                    <strong>
						<?php if ($feedbackType === 'success'): ?>
							Inspektion gemeldet
						<?php elseif ($feedbackType === 'error'): ?>
							Fehler
						<?php else: ?>
							Hinweis
						<?php endif; ?>
					</strong>
                    <span><?= htmlspecialchars($feedbackMessage); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($fahrzeuge)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-car-circle-xmark"></i>
                <p>Keine Fahrzeuge zugeordnet.</p>
                <span>Sobald dir ein Fahrzeug zugeteilt wurde, erscheinen hier Wartungsdetails und das Formular zur Inspektion.</span>
            </div>
        <?php else: ?>
            <div class="section-grid">
                <?php foreach ($fahrzeuge as $fahrzeug): ?>
                    <?php
                        $fahrzeugId = (int) $fahrzeug['FahrzeugID'];
                        $wartungen = $wartungenProFahrzeug[$fahrzeugId] ?? [];
                        $wartungenZukunft = [];
                        $wartungenHistorie = [];

                        foreach ($wartungen as $wartung) {
                            $datumRoh = $wartung['Wartungsdatum'] ?? null;
                            $datumObj = null;
                            if ($datumRoh) {
                                try {
                                    $datumObj = new DateTimeImmutable($datumRoh);
                                } catch (Exception $e) {
                                    $datumObj = null;
                                }
                            }

                            if ($datumObj && $datumObj >= $heute) {
                                $wartungenZukunft[] = $wartung;
                            } else {
                                $wartungenHistorie[] = $wartung;
                            }
                        }

                        usort($wartungenZukunft, function (array $a, array $b): int {
                            return strcmp((string) ($a['Wartungsdatum'] ?? ''), (string) ($b['Wartungsdatum'] ?? ''));
                        });

                        usort($wartungenHistorie, function (array $a, array $b): int {
                            return strcmp((string) ($b['Wartungsdatum'] ?? ''), (string) ($a['Wartungsdatum'] ?? ''));
                        });

                        $naechsteWartung = $wartungenZukunft[0] ?? null;
                        $letzteWartung = $wartungenHistorie[0] ?? null;

                        $naechsteWartungText = 'Keine Wartung geplant';
                        if ($naechsteWartung) {
                            $teile = [formatDate($naechsteWartung['Wartungsdatum'] ?? null)];
                            $beschreibung = trim((string) ($naechsteWartung['Beschreibung'] ?? ''));
                            if ($beschreibung !== '') {
                                $teile[] = $beschreibung;
                            }
                            $naechsteWartungText = implode(' · ', array_filter($teile));
                        }

                        $letzteWartungText = 'Noch keine Wartung erfasst';
                        if ($letzteWartung) {
                            $teile = [formatDate($letzteWartung['Wartungsdatum'] ?? null)];
                            $beschreibung = trim((string) ($letzteWartung['Beschreibung'] ?? ''));
                            if ($beschreibung !== '') {
                                $teile[] = $beschreibung;
                            }
                            $letzteWartungText = implode(' · ', array_filter($teile));
                        }

                        $kilometerInfo = $kilometerstaende[$fahrzeugId] ?? ['wert' => null, 'datum' => null];
                        $letzterKilometerwert = $kilometerInfo['wert'] ?? null;
                        $letzterKilometerDatum = $kilometerInfo['datum'] ?? null;
                        $letzterKilometerDatumText = null;
                        if ($letzterKilometerDatum) {
                            $formatiert = formatDateTime($letzterKilometerDatum);
                            $letzterKilometerDatumText = $formatiert !== 'Keine Angabe' ? $formatiert : null;
                        }
                        $postForVehicle = isset($_POST['fahrzeug_id']) && (int) $_POST['fahrzeug_id'] === $fahrzeugId;
                        $shouldPrefillFromPost = $feedbackType === 'error' && $postForVehicle;
                        $prefillMeldung = $shouldPrefillFromPost ? htmlspecialchars($_POST['meldung'] ?? '') : '';
                        $prefillRest = $shouldPrefillFromPost ? htmlspecialchars($_POST['rest_km'] ?? '') : '';
                        $prefillGesamt = $shouldPrefillFromPost ? htmlspecialchars($_POST['gesamt_km'] ?? '') : '';
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-car-side"></i> <?= htmlspecialchars(trim($fahrzeug['Marke'] . ' ' . $fahrzeug['Modell'])); ?></h2>
                        </div>
                        <div class="card-list">
                            <div class="card-item">
                                <div class="item-header">
                                    <div class="item-icon"><i class="fa-solid fa-id-badge"></i></div>
                                    <span class="item-label">Konzession</span>
                                </div>
                                <span class="item-value"><?= htmlspecialchars($fahrzeug['Konzessionsnummer']); ?></span>
                            </div>
                            <div class="card-item">
                                <div class="item-header">
                                    <div class="item-icon"><i class="fa-solid fa-car"></i></div>
                                    <span class="item-label">Kennzeichen</span>
                                </div>
                                <span class="item-value"><?= htmlspecialchars($fahrzeug['Kennzeichen']); ?></span>
                            </div>
                            <div class="card-item">
                                <div class="item-header">
                                    <div class="item-icon"><i class="fa-solid fa-calendar-check"></i></div>
                                    <span class="item-label">Nächste Wartung</span>
                                </div>
                                <span class="item-value"><?= htmlspecialchars($naechsteWartungText); ?></span>
                            </div>
                            <div class="card-item">
                                <div class="item-header">
                                    <div class="item-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                                    <span class="item-label">Letzte Wartung</span>
                                </div>
                                <span class="item-value"><?= htmlspecialchars($letzteWartungText); ?></span>
                            </div>
                            <div class="card-item">
                                <div class="item-header">
                                    <div class="item-icon"><i class="fa-solid fa-road"></i></div>
                                    <span class="item-label">Letzter Kilometerstand</span>
                                </div>
                                <span class="item-value">
                                    <?= htmlspecialchars(formatKilometer($letzterKilometerwert)); ?>
                                    <?php if ($letzterKilometerDatumText): ?>
                                        <span class="item-subtext">Stand: <?= htmlspecialchars($letzterKilometerDatumText); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-subsection">
                            <h3><i class="fa-solid fa-list-check"></i> Wartungsdetails</h3>
                            <?php if (!empty($wartungenZukunft)): ?>
                                <ul class="maintenance-list">
                                    <?php foreach ($wartungenZukunft as $wartung): ?>
                                        <li class="maintenance-entry">
                                            <div class="maintenance-date">
                                                <i class="fa-solid fa-calendar-day"></i>
                                                <span><?= htmlspecialchars(formatDate($wartung['Wartungsdatum'] ?? null)); ?></span>
                                            </div>
                                            <div class="maintenance-body">
                                                <strong><?= htmlspecialchars($wartung['Beschreibung'] ?? 'Ohne Beschreibung'); ?></strong>
                                                <?php if (!empty($wartung['Werkstatt'])): ?>
                                                    <span><?= htmlspecialchars($wartung['Werkstatt']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($wartung['Bemerkungen'])): ?>
                                                    <p><?= nl2br(htmlspecialchars($wartung['Bemerkungen'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="empty-substate">Keine zukünftigen Wartungen geplant.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-subsection">
                            <h3><i class="fa-solid fa-paper-plane"></i> Inspektion melden</h3>
                            <form method="POST" class="inspection-form" data-fahrzeug-id="<?= $fahrzeugId; ?>" data-last-km="<?= htmlspecialchars($letzterKilometerwert !== null ? (string) $letzterKilometerwert : ''); ?>" novalidate>
                                <input type="hidden" name="fahrzeug_id" value="<?= $fahrzeugId; ?>">
                                <input type="hidden" name="konzession" value="<?= htmlspecialchars($fahrzeug['Konzessionsnummer']); ?>">
                                <input type="hidden" name="kennzeichen" value="<?= htmlspecialchars($fahrzeug['Kennzeichen']); ?>">
                                <div class="form-grid">
                                    <div class="input-group">
                                        <label for="meldung-<?= $fahrzeugId; ?>">Anliegen</label>
                                        <textarea id="meldung-<?= $fahrzeugId; ?>" name="meldung" rows="2" maxlength="240" placeholder="z. B. Inspektion oder Ölservice" required><?= $prefillMeldung; ?></textarea>
                                        <p class="form-hint">Beschreibe kurz, was geprüft werden soll.</p>
                                        <p class="form-feedback" aria-live="polite"></p>
                                    </div>
                                    <div class="input-group">
                                        <label for="rest-km-<?= $fahrzeugId; ?>">Restkilometer</label>
                                        <input type="number" id="rest-km-<?= $fahrzeugId; ?>" name="rest_km" min="0" max="50000" step="50" inputmode="numeric" placeholder="z. B. 1 200" value="<?= $prefillRest; ?>" required>
                                        <p class="form-hint">Werte unter 500 km gelten als dringlich.</p>
                                        <p class="form-feedback" aria-live="polite"></p>
                                    </div>
                                    <div class="input-group">
                                        <label for="gesamt-km-<?= $fahrzeugId; ?>">Gesamtkilometerstand</label>
                                        <input type="number" id="gesamt-km-<?= $fahrzeugId; ?>" name="gesamt_km" min="0" max="1000000" step="1" inputmode="numeric" placeholder="z.&nbsp;B.&nbsp;174&nbsp;500" value="<?= $prefillGesamt; ?>" required>
                                        <?php if ($letzterKilometerwert !== null): ?>
                                            <p class="form-hint">
                                                Zuletzt gespeichert: <?= htmlspecialchars(formatKilometer($letzterKilometerwert)); ?>
                                                <?php if ($letzterKilometerDatumText): ?>
                                                    am <?= htmlspecialchars($letzterKilometerDatumText); ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="form-hint">Trage den aktuellen Kilometerstand aus dem Taxameter ein.</p>
                                        <?php endif; ?>
                                        <p class="form-feedback" aria-live="polite"></p>
                                    </div>
                                </div>
                                <button type="submit" name="inspektion_melden">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Inspektion melden
                                </button>
                            </form>
                        </div>
                        <div class="card-subsection">
                            <h3><i class="fa-solid fa-clock-rotate-left"></i> Wartungshistorie</h3>
                            <?php if (!empty($wartungenHistorie)): ?>
                                <ul class="maintenance-history">
                                    <?php foreach ($wartungenHistorie as $wartung): ?>
                                        <li class="maintenance-entry">
                                            <div class="maintenance-date">
                                                <i class="fa-solid fa-calendar-day"></i>
                                                <span><?= htmlspecialchars(formatDate($wartung['Wartungsdatum'] ?? null)); ?></span>
                                            </div>
                                            <div class="maintenance-body">
                                                <strong><?= htmlspecialchars($wartung['Beschreibung'] ?? 'Ohne Beschreibung'); ?></strong>
                                                <?php if (!empty($wartung['Werkstatt'])): ?>
                                                    <span><?= htmlspecialchars($wartung['Werkstatt']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($wartung['Bemerkungen'])): ?>
                                                    <p><?= nl2br(htmlspecialchars($wartung['Bemerkungen'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="empty-substate">Noch keine Wartungen erfasst.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'bottom_nav.php'; ?>

    <script>
        (function () {
            const numberFormatter = new Intl.NumberFormat('de-DE');

            document.querySelectorAll('.inspection-form').forEach((form) => {
                const fahrzeugId = form.dataset.fahrzeugId;
                const lastMileage = Number(form.dataset.lastKm || '0');
                const fields = form.querySelectorAll('input, textarea');
                let storageAvailable = false;

                try {
                    const testKey = '__drive-inspection-storage__';
                    localStorage.setItem(testKey, '1');
                    localStorage.removeItem(testKey);
                    storageAvailable = true;
                } catch (error) {
                    storageAvailable = false;
                }

                const ensureMileageValidity = (input) => {
                    if (input.name !== 'gesamt_km') {
                        input.setCustomValidity('');
                        return;
                    }

                    if (lastMileage > 0 && input.value) {
                        const value = Number(input.value);
                        if (Number.isFinite(value) && value <= lastMileage) {
                            input.setCustomValidity(`Der Kilometerstand muss größer sein als ${numberFormatter.format(lastMileage)} km.`);
                        } else {
                            input.setCustomValidity('');
                        }
                    } else {
                        input.setCustomValidity('');
                    }
                };

                const updateFeedback = (input) => {
                    const feedbackEl = input.closest('.input-group').querySelector('.form-feedback');
                    if (!feedbackEl) {
                        return;
                    }

                    ensureMileageValidity(input);

                    feedbackEl.textContent = '';
                    feedbackEl.classList.remove('is-valid', 'is-error');

                    if (!input.value.trim()) {
                        return;
                    }

                    if (input.checkValidity()) {
                        let positiveMessage = 'Sieht gut aus.';
                        if (input.name === 'rest_km' && Number(input.value) < 500) {
                            positiveMessage = 'Dringend: Bitte Verwaltung zeitnah informieren.';
                        } else if (input.name === 'gesamt_km' && lastMileage > 0) {
                            positiveMessage = `Neuer Kilometerstand (${numberFormatter.format(Number(input.value))} km) wird gespeichert.`;
                        }
                        feedbackEl.textContent = positiveMessage;
                        feedbackEl.classList.add('is-valid');
                    } else {
                        feedbackEl.textContent = input.validationMessage;
                        feedbackEl.classList.add('is-error');
                    }
                };

                fields.forEach((input) => {
                    input.addEventListener('input', () => updateFeedback(input));
                    input.addEventListener('blur', () => updateFeedback(input));
                });

                const mileageInput = form.querySelector('input[name="gesamt_km"]');
                if (mileageInput && storageAvailable) {
                    const storageKey = `fahrzeug-${fahrzeugId}-gesamtKm`;
                    const storedValue = localStorage.getItem(storageKey);
                    if (!mileageInput.value && storedValue) {
                        mileageInput.value = storedValue;
                        updateFeedback(mileageInput);
                    }

                    mileageInput.addEventListener('change', () => {
                        ensureMileageValidity(mileageInput);
                        if (mileageInput.checkValidity() && mileageInput.value) {
                            localStorage.setItem(storageKey, mileageInput.value);
                        }
                    });
                }

                form.addEventListener('submit', (event) => {
                    let hasError = false;

                    fields.forEach((input) => {
                        ensureMileageValidity(input);
                        if (!input.checkValidity()) {
                            hasError = true;
                            input.reportValidity();
                            const feedbackEl = input.closest('.input-group').querySelector('.form-feedback');
                            if (feedbackEl) {
                                feedbackEl.textContent = input.validationMessage;
                                feedbackEl.classList.add('is-error');
                            }
                        }
                    });

                    if (hasError) {
                        event.preventDefault();
                    } else {
                        form.classList.add('is-loading');
                    }
                });
            });
        }());
    </script>
</body>
</html>
