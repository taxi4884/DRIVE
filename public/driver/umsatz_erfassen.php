<?php
require_once '../../includes/head.php';

if ($_SESSION['user_role'] !== 'fahrer') {
    header("Location: ../index.php");
    exit();
}

$fahrer_id = $_SESSION['user_id'];

// Recherche-Flag und Schichtziel des Fahrers laden
$recherche_flag = 0;
$standardSchichtziel = 0.0;
$abrechnungsart = 'alt';
$fahrerPersonalnummer = null;
$fahrerNummer = null;
$fahrerFmsAlias = null;
$fahrerCompanyId = null;
$fahrerCompanyName = null;
$neuApiError = null;
$neuFahrten = [];
$neuFahrtenMeta = [];

try {
    $stmtRecherche = $pdo->prepare("
        SELECT 
            COALESCE(recherche, 0) AS recherche,
            COALESCE(standard_schichtziel, 0) AS standard_schichtziel,
            COALESCE(NULLIF(TRIM(abrechnungsart), ''), 'alt') AS abrechnungsart,
            NULLIF(TRIM(Personalnummer), '') AS personalnummer,
            NULLIF(TRIM(Fahrernummer), '') AS fahrernummer,
            NULLIF(TRIM(fms_alias), '') AS fms_alias,
            company_id
        FROM Fahrer
        WHERE FahrerID = :fahrer_id
        LIMIT 1
    ");
    $stmtRecherche->execute(['fahrer_id' => $fahrer_id]);
    $row = $stmtRecherche->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $recherche_flag = (int) $row['recherche'];
        $standardSchichtziel = (float) $row['standard_schichtziel'];
        $abrechnungsart = strtolower((string)($row['abrechnungsart'] ?? 'alt'));
        $fahrerPersonalnummer = $row['personalnummer'] ?? null;
        $fahrerNummer = $row['fahrernummer'] ?? null;
        $fahrerFmsAlias = $row['fms_alias'] ?? null;
        $fahrerCompanyId = isset($row['company_id']) ? (int)$row['company_id'] : null;
        if ($fahrerCompanyId) {
            try {
                $stmtCompany = $pdo->prepare('SELECT name FROM companies WHERE id = ? LIMIT 1');
                $stmtCompany->execute([$fahrerCompanyId]);
                $fahrerCompanyName = $stmtCompany->fetchColumn() ?: null;
            } catch (Throwable $e) {
                $fahrerCompanyName = null;
            }
        }
    }
} catch (Exception $e) {
    // Optional: Logging; UI soll nicht brechen, wenn Flag oder Ziel nicht geladen werden können
    $recherche_flag = 0;
    $standardSchichtziel = 0.0;
}

if ($abrechnungsart === 'neu') {
    header('Location: abrechnung_neu.php');
    exit();
}

if (!function_exists('driverApiReadEnv')) {
    function driverApiReadEnv(string $key, ?string $fallback = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }

        static $envValues = null;
        if ($envValues === null) {
            $envValues = [];
            $envFile = realpath(__DIR__ . '/../../includes/.env');
            if ($envFile && is_readable($envFile)) {
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $envValues[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                }
            }
        }

        if (isset($envValues[$key]) && trim((string)$envValues[$key]) !== '') {
            return trim((string)$envValues[$key]);
        }

        return $fallback;
    }
}

if (!function_exists('driverApiEnvByCompany')) {
    function driverApiEnvByCompany(string $baseKey, ?int $companyId = null, ?string $companyName = null, ?string $fallback = null): ?string
    {
        $candidates = [];
        if ($companyId !== null && $companyId > 0) {
            $candidates[] = $baseKey . '_COMPANY_' . $companyId;
            $candidates[] = $baseKey . '_' . $companyId;
        }
        if ($companyName !== null && trim($companyName) !== '') {
            $slug = strtoupper((string)preg_replace('/[^A-Z0-9]+/', '_', strtoupper($companyName)));
            $slug = trim($slug, '_');
            if ($slug !== '') {
                $candidates[] = $baseKey . '_COMPANY_' . $slug;
                $candidates[] = $baseKey . '_' . $slug;
            }
        }
        $candidates[] = $baseKey;

        foreach ($candidates as $key) {
            $val = driverApiReadEnv($key);
            if ($val !== null && trim($val) !== '') {
                return $val;
            }
        }
        return $fallback;
    }
}

if (!function_exists('driverApiFetchNeuFahrten')) {
    function driverApiFetchNeuFahrten(string $personalnummer, string $dateYmd, ?int $companyId = null, ?string $companyName = null): array
    {
        $tokenUrl = driverApiEnvByCompany('TAXIDATEN_TOKEN_URL', $companyId, $companyName, 'https://extern.taxidaten.com/token');
        $odataBase = rtrim((string)driverApiEnvByCompany('TAXIDATEN_ODATA_BASE', $companyId, $companyName, 'https://extern.taxidaten.com/odata'), '/');
        $apiUser = driverApiEnvByCompany('TAXIDATEN_API_USERNAME', $companyId, $companyName);
        $apiPass = driverApiEnvByCompany('TAXIDATEN_API_PASSWORD', $companyId, $companyName);
        $externalUser = driverApiEnvByCompany('TAXIDATEN_EXTERNAL_USER', $companyId, $companyName);

        if (!$apiUser || !$apiPass) {
            throw new RuntimeException('TSE-API Zugangsdaten fehlen (TAXIDATEN_API_USERNAME / TAXIDATEN_API_PASSWORD).');
        }

        if (!$externalUser) {
            $externalUser = base64_encode($apiUser);
        }

        $tokenCh = curl_init($tokenUrl);
        curl_setopt_array($tokenCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'password',
                'username' => $apiUser,
                'password' => $apiPass,
            ]),
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $tokenResp = curl_exec($tokenCh);
        $tokenHttp = (int)curl_getinfo($tokenCh, CURLINFO_HTTP_CODE);
        $tokenErr = curl_error($tokenCh);
        curl_close($tokenCh);

        if ($tokenResp === false || $tokenHttp < 200 || $tokenHttp >= 300) {
            throw new RuntimeException('Tokenabruf fehlgeschlagen (HTTP ' . $tokenHttp . '): ' . ($tokenErr ?: 'keine Antwort'));
        }

        $tokenJson = json_decode((string)$tokenResp, true);
        $accessToken = is_array($tokenJson) ? ($tokenJson['access_token'] ?? null) : null;
        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw new RuntimeException('Kein access_token in der TSE-API Antwort.');
        }

        $fahrtenUrl = $odataBase . '/fahrten?%24orderby=id%20desc&%24top=300&%24filter=' . rawurlencode("persnr eq '{$personalnummer}'");
        $fahrtenCh = curl_init($fahrtenUrl);
        curl_setopt_array($fahrtenCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
                'ExternalUser: ' . $externalUser,
            ],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $fahrtenResp = curl_exec($fahrtenCh);
        $fahrtenHttp = (int)curl_getinfo($fahrtenCh, CURLINFO_HTTP_CODE);
        $fahrtenErr = curl_error($fahrtenCh);
        curl_close($fahrtenCh);

        if ($fahrtenResp === false || $fahrtenHttp < 200 || $fahrtenHttp >= 300) {
            throw new RuntimeException('Fahrtenabruf fehlgeschlagen (HTTP ' . $fahrtenHttp . '): ' . ($fahrtenErr ?: 'keine Antwort'));
        }

        $fahrtenJson = json_decode((string)$fahrtenResp, true);
        $rows = [];
        if (is_array($fahrtenJson)) {
            if (isset($fahrtenJson['value']) && is_array($fahrtenJson['value'])) {
                $rows = $fahrtenJson['value'];
            } elseif (array_keys($fahrtenJson) === range(0, count($fahrtenJson) - 1)) {
                $rows = $fahrtenJson;
            }
        }

        $dateCandidates = ['datum', 'date', 'zeit', 'zeitpunkt', 'startzeit', 'beginn', 'created_at'];
        $moneyCandidates = ['fahrpreis', 'betrag', 'preis', 'umsatz', 'brutto', 'summe'];

        $filtered = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;

            $credit = $row['kreditfahrt'] ?? null;
            if ($credit === true || $credit === 1 || $credit === '1') {
                continue;
            }

            $art = isset($row['art']) ? strtoupper(trim((string)$row['art'])) : null;
            if ($art !== null && $art !== '' && $art !== 'NF') {
                continue;
            }

            $rideDate = null;
            foreach ($dateCandidates as $candidate) {
                if (!isset($row[$candidate]) || $row[$candidate] === null || $row[$candidate] === '') continue;
                $ts = strtotime((string)$row[$candidate]);
                if ($ts !== false) {
                    $rideDate = date('Y-m-d', $ts);
                    break;
                }
            }
            if ($rideDate !== null && $rideDate !== $dateYmd) {
                continue;
            }

            $money = null;
            foreach ($moneyCandidates as $candidate) {
                if (!isset($row[$candidate])) continue;
                $val = $row[$candidate];
                if (is_numeric($val)) {
                    $money = (float)$val;
                    break;
                }
                if (is_string($val)) {
                    $norm = str_replace([',', ' '], ['.', ''], $val);
                    if (is_numeric($norm)) {
                        $money = (float)$norm;
                        break;
                    }
                }
            }

            $filtered[] = [
                'id' => $row['id'] ?? null,
                'art' => $row['art'] ?? null,
                'kreditfahrt' => $row['kreditfahrt'] ?? null,
                'e_link' => $row['e_link'] ?? null,
                'von' => $row['von'] ?? ($row['start'] ?? ($row['abholort'] ?? '')),
                'nach' => $row['nach'] ?? ($row['ziel'] ?? ''),
                'zeit' => $row['zeit'] ?? ($row['datum'] ?? ($row['startzeit'] ?? '')),
                'betrag' => $money,
                'raw' => $row,
            ];
        }

        $sum = 0.0;
        foreach ($filtered as $ride) {
            if (is_numeric($ride['betrag'])) {
                $sum += (float)$ride['betrag'];
            }
        }

        return [
            'fahrten' => $filtered,
            'summe' => round($sum, 2),
            'gesamt' => count($filtered),
            'odata_url' => $fahrtenUrl,
        ];
    }
}

// Datumsauswahl: bewusst keine Prüfung offener Schichten mehr.
// Fahrer wählen das Datum direkt über den Browser-Kalender.

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

$errorMessages = [];
$fieldErrors = [];
$formData = [
    'datum' => isset($_POST['datum']) ? trim((string) $_POST['datum']) : '',
    'taxameter_umsatz' => isset($_POST['taxameter_umsatz']) ? trim((string) $_POST['taxameter_umsatz']) : '',
    'ohne_taxameter' => isset($_POST['ohne_taxameter']) ? trim((string) $_POST['ohne_taxameter']) : '',
    'kartenzahlung' => isset($_POST['kartenzahlung']) ? trim((string) $_POST['kartenzahlung']) : '',
    'rechnungsfahrten' => isset($_POST['rechnungsfahrten']) ? trim((string) $_POST['rechnungsfahrten']) : '',
    'krankenfahrten' => isset($_POST['krankenfahrten']) ? trim((string) $_POST['krankenfahrten']) : '',
    'gutscheine' => isset($_POST['gutscheine']) ? trim((string) $_POST['gutscheine']) : '',
    'alita' => isset($_POST['alita']) ? trim((string) $_POST['alita']) : '',
    'tanken_waschen' => isset($_POST['tanken_waschen']) ? trim((string) $_POST['tanken_waschen']) : '',
    'sonstige_ausgaben' => isset($_POST['sonstige_ausgaben']) ? trim((string) $_POST['sonstige_ausgaben']) : '',
    'notiz' => isset($_POST['notiz']) ? trim((string) $_POST['notiz']) : '',
];

if ($formData['datum'] === '') {
    $jetzt = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
    $standardDatum = $jetzt->format('Y-m-d');

    if ((int) $jetzt->format('H') < 6) {
        $standardDatum = $jetzt->sub(new DateInterval('P1D'))->format('Y-m-d');
    }

    $formData['datum'] = $standardDatum;
}

if ($abrechnungsart === 'neu') {
    $referenceNumber = $fahrerPersonalnummer ?: ($fahrerNummer ?: $fahrerFmsAlias);
    if (!$referenceNumber) {
        $neuApiError = 'Für Abrechnungsart "neu" fehlt Personalnummer/Fahrernummer/FMS-Alias.';
    } else {
        try {
            $neuData = driverApiFetchNeuFahrten((string)$referenceNumber, (string)$formData['datum'], $fahrerCompanyId, $fahrerCompanyName);
            $neuFahrten = $neuData['fahrten'] ?? [];
            $neuFahrtenMeta = $neuData;
        } catch (Throwable $t) {
            $neuApiError = $t->getMessage();
        }
    }
}

// Formular wurde abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxBetrag = 10000;
    $sanitizedAmounts = [
        'taxameter_umsatz' => 0.0,
        'ohne_taxameter' => 0.0,
        'kartenzahlung' => 0.0,
        'rechnungsfahrten' => 0.0,
        'krankenfahrten' => 0.0,
        'gutscheine' => 0.0,
        'alita' => 0.0,
        'tanken_waschen' => 0.0,
        'sonstige_ausgaben' => 0.0,
    ];

    $datumInput = $formData['datum'] ?? '';
    if ($datumInput === '') {
        $fieldErrors['datum'] = 'Bitte ein Datum auswählen.';
    } else {
        $appTimezone = new DateTimeZone('Europe/Berlin');
        $datumObj = DateTimeImmutable::createFromFormat('!Y-m-d', $datumInput, $appTimezone);
        if (!$datumObj || $datumObj->format('Y-m-d') !== $datumInput) {
            $fieldErrors['datum'] = 'Bitte ein gültiges Datum im Format JJJJ-MM-TT wählen.';
        } else {
            $heute = (new DateTimeImmutable('now', $appTimezone))->setTime(0, 0);
            if ($datumObj > $heute) {
                $fieldErrors['datum'] = 'Das Datum darf nicht in der Zukunft liegen.';
            } else {
                $formData['datum'] = $datumObj->format('Y-m-d');
            }
        }
    }

    $betragFelder = [
        'taxameter_umsatz' => 'Umsatz mit Taxameter',
        'ohne_taxameter' => 'Umsatz ohne Taxameter',
        'kartenzahlung' => 'Kartenzahlungen',
        'rechnungsfahrten' => 'Rechnungsfahrten',
        'krankenfahrten' => 'Krankenfahrten',
        'gutscheine' => 'Gutscheine',
        'alita' => 'Alita',
        'tanken_waschen' => 'Tanken/Waschen',
        'sonstige_ausgaben' => 'Sonstige Ausgaben',
    ];

    foreach ($betragFelder as $feld => $label) {
        $wert = $formData[$feld];

        if ($wert === '') {
            $sanitizedAmounts[$feld] = 0.0;
            continue;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $wert);
        $betrag = filter_var($normalized, FILTER_VALIDATE_FLOAT);

        if ($betrag === false) {
            $fieldErrors[$feld] = "Bitte einen gültigen Betrag für {$label} eingeben.";
            continue;
        }

        if ($betrag < 0) {
            $fieldErrors[$feld] = "{$label} darf nicht negativ sein.";
            continue;
        }

        if ($betrag > $maxBetrag) {
            $fieldErrors[$feld] = "{$label} darf den Betrag von " . number_format($maxBetrag, 2, ',', '.') . " € nicht überschreiten.";
            continue;
        }

        $sanitizedAmounts[$feld] = round($betrag, 2);
        $formData[$feld] = number_format($sanitizedAmounts[$feld], 2, '.', '');
    }

    if ($sanitizedAmounts['taxameter_umsatz'] <= 0 && $sanitizedAmounts['ohne_taxameter'] <= 0) {
        $meldung = 'Bitte mindestens einen Umsatz (mit oder ohne Taxameter) eingeben.';
        $fieldErrors['taxameter_umsatz'] = $meldung;
        $fieldErrors['ohne_taxameter'] = $meldung;
    }

    if (empty($fieldErrors['datum'])) {
        $duplikatStmt = $pdo->prepare('SELECT COUNT(*) FROM Umsatz WHERE FahrerID = ? AND Datum = ?');
        $duplikatStmt->execute([$fahrer_id, $formData['datum']]);
        if ($duplikatStmt->fetchColumn() > 0) {
            $fieldErrors['datum'] = 'Für dieses Datum wurde bereits ein Umsatz erfasst.';
        }
    }

    $notiz = $formData['notiz'] !== '' ? $formData['notiz'] : null;

    if (empty($fieldErrors)) {
        try {
            $fahrer_stmt = $pdo->prepare('SELECT Vorname, Nachname FROM Fahrer WHERE FahrerID = ?');
            $fahrer_stmt->execute([$fahrer_id]);
            $fahrer = $fahrer_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fahrer) {
                throw new RuntimeException('Fahrer nicht gefunden.');
            }

            $pdo->beginTransaction();

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
                $formData['datum'],
                $sanitizedAmounts['taxameter_umsatz'],
                $sanitizedAmounts['ohne_taxameter'],
                $sanitizedAmounts['kartenzahlung'],
                $sanitizedAmounts['rechnungsfahrten'],
                $sanitizedAmounts['krankenfahrten'],
                $sanitizedAmounts['gutscheine'],
                $sanitizedAmounts['alita'],
                $sanitizedAmounts['tanken_waschen'],
                $sanitizedAmounts['sonstige_ausgaben'],
                $notiz
            ]);

            $umsatzGesamt = $sanitizedAmounts['taxameter_umsatz'] + $sanitizedAmounts['ohne_taxameter'];

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
                $fahrer['Vorname'],
                $fahrer['Nachname'],
                $umsatzGesamt,
                $formData['datum'],
                0
            ]);

            $pdo->commit();

            header('Location: dashboard.php');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessages[] = 'Beim Speichern ist ein Fehler aufgetreten. Bitte versuche es erneut.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Umsatz erfassen | DRIVE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/driver-dashboard.css">
    <link rel="stylesheet" href="css/umsatz.css">
    <link rel="stylesheet" href="css/form-feedback.css">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
</head>
<body>
    <?php include 'bottom_nav.php'; ?>
    <main>
        <h1>Umsatz erfassen</h1>
        <?php if (!empty($errorMessages)): ?>
            <div class="form-feedback form-feedback--error">
                <ul>
                    <?php foreach ($errorMessages as $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="umsatzForm" action="umsatz_erfassen.php" method="POST">
            <?php
            $aktuelleStunde = (int) date('H');
            if ($aktuelleStunde < 6) {
                echo '<div class="form-feedback form-feedback--warning">⚠️ Bitte achte auf das richtige Datum! (Aktuell vor 06:00 Uhr)</div>';
            }
            ?>

            <?php if ($recherche_flag === 1): ?>
                <p class="recherche-hinweis">
                    <a href="recherche.php" class="recherche-link">Auftragsrecherche</a>
                </p>
            <?php endif; ?>

            <?php if ($abrechnungsart === 'neu'): ?>
                <div class="form-feedback <?= $neuApiError ? 'form-feedback--warning' : 'form-feedback--success' ?>" style="margin-bottom: 12px;">
                    <strong>TSE / WebAPI (neu):</strong>
                    <?php if ($neuApiError): ?>
                        <?= htmlspecialchars($neuApiError) ?>
                    <?php else: ?>
                        Heute erkannte Fahrten: <strong><?= (int)($neuFahrtenMeta['gesamt'] ?? 0) ?></strong>
                        <?php if (isset($neuFahrtenMeta['summe'])): ?>
                            · Summe (erkannte Beträge): <strong><?= number_format((float)$neuFahrtenMeta['summe'], 2, ',', '.') ?> €</strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!$neuApiError && !empty($neuFahrten)): ?>
                    <details style="margin-bottom: 14px;">
                        <summary><strong>Heute gefahrene Fahrten anzeigen</strong></summary>
                        <div style="overflow:auto; margin-top:8px;">
                            <table class="table table-sm" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; border-bottom:1px solid #ddd;">ID</th>
                                        <th style="text-align:left; border-bottom:1px solid #ddd;">Zeit</th>
                                        <th style="text-align:left; border-bottom:1px solid #ddd;">Von</th>
                                        <th style="text-align:left; border-bottom:1px solid #ddd;">Nach</th>
                                        <th style="text-align:left; border-bottom:1px solid #ddd;">Art</th>
                                        <th style="text-align:right; border-bottom:1px solid #ddd;">Betrag</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($neuFahrten as $fahrt): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)($fahrt['id'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($fahrt['zeit'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($fahrt['von'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($fahrt['nach'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($fahrt['art'] ?? '')) ?></td>
                                            <td style="text-align:right;"><?= isset($fahrt['betrag']) && $fahrt['betrag'] !== null ? number_format((float)$fahrt['betrag'], 2, ',', '.') . ' €' : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endif; ?>

            <label for="datum">Datum:</label>
            <input
                type="date"
                id="datum"
                name="datum"
                value="<?= htmlspecialchars($formData['datum']) ?>"
                max="<?= htmlspecialchars((new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d')) ?>"
                required
            >
            <?php if (!empty($fieldErrors['datum'])): ?>
                <p class="field-error"><?= htmlspecialchars($fieldErrors['datum']) ?></p>
            <?php endif; ?>

            <?php if ($standardSchichtziel > 0): ?>
                <div class="schichtziel-hinweis" data-schichtziel="<?= htmlspecialchars($standardSchichtziel, ENT_QUOTES, 'UTF-8') ?>">
                    <p>
                        <i class="fa-solid fa-bullseye"></i>
                        Dein persönliches Schichtziel liegt bei
                        <strong><?= number_format($standardSchichtziel, 2, ',', '.') ?> €</strong>.
                    </p>
                    <p id="schichtziel-progress-text" class="schichtziel-progress-text">
                        Noch keine Einnahmen erfasst.
                    </p>
                </div>
            <?php else: ?>
                <div class="schichtziel-hinweis schichtziel-hinweis--info">
                    <p>
                        <i class="fa-regular fa-circle-question"></i>
                        In deinen persönlichen Daten kannst du dir ein Schichtziel eintragen – nur für dich,
                        als kleine Orientierung für deine Schicht.
                    </p>
                </div>
            <?php endif; ?>

            <fieldset class="gruen">
                <legend>Bargeld-Einnahmen</legend>

                <label for="taxameter">Umsatz mit Taxameter (€):</label>
                <input type="number" id="taxameter" name="taxameter_umsatz" step="0.01" min="0" value="<?= htmlspecialchars($formData['taxameter_umsatz']) ?>">
                <?php if (!empty($fieldErrors['taxameter_umsatz'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['taxameter_umsatz']) ?></p>
                <?php endif; ?>

                <label for="ohne_taxameter">Umsatz ohne Taxameter (€):</label>
                <input type="number" id="ohne_taxameter" name="ohne_taxameter" step="0.01" min="0" value="<?= htmlspecialchars($formData['ohne_taxameter']) ?>">
                <?php if (!empty($fieldErrors['ohne_taxameter'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['ohne_taxameter']) ?></p>
                <?php endif; ?>
            </fieldset>

            <fieldset class="blau">
                <legend>Bargeldlose Umsätze</legend>

                <label for="kartenzahlung">Kartenzahlungen (€):</label>
                <input type="number" id="kartenzahlung" name="kartenzahlung" step="0.01" min="0" value="<?= htmlspecialchars($formData['kartenzahlung']) ?>">
                <?php if (!empty($fieldErrors['kartenzahlung'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['kartenzahlung']) ?></p>
                <?php endif; ?>

                <label for="rechnungsfahrten">Rechnungsfahrten (€):</label>
                <input type="number" id="rechnungsfahrten" name="rechnungsfahrten" step="0.01" min="0" value="<?= htmlspecialchars($formData['rechnungsfahrten']) ?>">
                <?php if (!empty($fieldErrors['rechnungsfahrten'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['rechnungsfahrten']) ?></p>
                <?php endif; ?>

                <label for="krankenfahrten">Krankenfahrten ohne Zuzahlung (€):</label>
                <input type="number" id="krankenfahrten" name="krankenfahrten" step="0.01" min="0" value="<?= htmlspecialchars($formData['krankenfahrten']) ?>">
                <?php if (!empty($fieldErrors['krankenfahrten'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['krankenfahrten']) ?></p>
                <?php endif; ?>

                <label for="gutscheine">Gutscheine (€):</label>
                <input type="number" id="gutscheine" name="gutscheine" step="0.01" min="0" value="<?= htmlspecialchars($formData['gutscheine']) ?>">
                <?php if (!empty($fieldErrors['gutscheine'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['gutscheine']) ?></p>
                <?php endif; ?>

                <label for="alita">Alita (€):</label>
                <input type="number" id="alita" name="alita" step="0.01" min="0" value="<?= htmlspecialchars($formData['alita']) ?>">
                <?php if (!empty($fieldErrors['alita'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['alita']) ?></p>
                <?php endif; ?>
            </fieldset>

            <fieldset class="rot">
                <legend>Ausgaben</legend>

                <label for="tanken_waschen">Tanken/Waschen (€):</label>
                <input type="number" id="tanken_waschen" name="tanken_waschen" step="0.01" min="0" value="<?= htmlspecialchars($formData['tanken_waschen']) ?>">
                <?php if (!empty($fieldErrors['tanken_waschen'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['tanken_waschen']) ?></p>
                <?php endif; ?>

                <label for="sonstige_ausgaben">Sonstige Ausgaben (€):</label>
                <input type="number" id="sonstige_ausgaben" name="sonstige_ausgaben" step="0.01" min="0" value="<?= htmlspecialchars($formData['sonstige_ausgaben']) ?>">
                <?php if (!empty($fieldErrors['sonstige_ausgaben'])): ?>
                    <p class="field-error"><?= htmlspecialchars($fieldErrors['sonstige_ausgaben']) ?></p>
                <?php endif; ?>
            </fieldset>

            <fieldset class="gelb">
                <legend>Übriges Bargeld</legend>

                <label for="gesamtumsatz">Bargeld (€):</label>
                <input type="text" id="gesamtumsatz" readonly>
            </fieldset>

            <label for="notiz">Notiz (optional):</label>
            <textarea id="notiz" name="notiz" rows="4" cols="50"><?= htmlspecialchars($formData['notiz']) ?></textarea>

            <button type="submit">Umsatz speichern</button>
        </form>

        <div id="overlay">
            <div class="reward-icon" id="reward-icon"></div>
            <p id="overlay-main-text">Umsatz wird gespeichert...</p>
            <p id="overlay-sub-text" class="overlay-sub-text"></p>
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
            formId: 'umsatzForm',
            overlayId: 'overlay',
            submitDelay: 2000
        });
    })();
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const zielWrapper = document.querySelector('.schichtziel-hinweis[data-schichtziel]');
        const feldTaxameter = document.getElementById('taxameter');
        const feldOhneTaxameter = document.getElementById('ohne_taxameter');
        const overlaySub = document.getElementById('overlay-sub-text');
        const rewardIcon = document.getElementById('reward-icon');

        let zielWert = 0;
        if (zielWrapper) {
            zielWert = parseFloat(zielWrapper.dataset.schichtziel || '0');
        }

        function parseEuro(input) {
            if (!input) return 0;
            const raw = String(input.value || '')
                .replace(/\s/g, '')
                .replace(/\./g, '')
                .replace(/,/g, '.');
            const val = parseFloat(raw);
            return isNaN(val) ? 0 : val;
        }

        const texteUnterZiel = [
            "Gute Schicht – du bist auf Kurs!",
            "Solide gefahren, weiter so!",
            "Du hast schon was bewegt!",
            "Saubere Arbeit – der Tag läuft!",
            "Gute Basis – du baust auf!"
        ];

        const texteKrone = [
            "Ziel erreicht – starke Schicht! 👑",
            "Top gefahren – Krone verdient! 👑",
            "Stabiler Tag – du hast’s erreicht! 👑",
            "Sehr gut! Du hast dein Ziel geknackt! 👑",
            "Super Leistung – Krone für dich! 👑"
        ];

        const texteDiamant = [
            "Mega gefahren – Diamantschicht! 💎",
            "Überragend – das funkelt! 💎",
            "Das ist Champions-Level! 💎",
            "Brutal gute Schicht – Diamant! 💎",
            "Wahnsinn! Du glänzt heute! 💎"
        ];

        function randomText(arr) {
            return arr[Math.floor(Math.random() * arr.length)];
        }

        // Live-Text unter dem Schichtziel
        const progressText = document.getElementById('schichtziel-progress-text');

        function updateSchichtzielStatus() {
            if (!progressText || !zielWert || zielWert <= 0) {
                return;
            }

            const summe = parseEuro(feldTaxameter) + parseEuro(feldOhneTaxameter);

            if (summe <= 0) {
                progressText.textContent = 'Noch keine Einnahmen erfasst.';
                return;
            }

            const prozent = Math.max(0, Math.min(300, (summe / zielWert) * 100));

            if (prozent < 50) {
                progressText.textContent = `Du hast etwa ${prozent.toFixed(0)} % deines Schichtziels erreicht.`;
            } else if (prozent < 100) {
                progressText.textContent = `Stark, du bist bei etwa ${prozent.toFixed(0)} % deines Schichtziels.`;
            } else if (prozent < 150) {
                progressText.textContent = `Glückwunsch! Dein Schichtziel ist erreicht (ca. ${prozent.toFixed(0)} %).`;
            } else {
                progressText.textContent = `Überragend – du liegst deutlich über deinem Schichtziel (ca. ${prozent.toFixed(0)} %).`;
            }
        }

        if (zielWert > 0) {
            ['input', 'change'].forEach(eventName => {
                if (feldTaxameter) {
                    feldTaxameter.addEventListener(eventName, updateSchichtzielStatus);
                }
                if (feldOhneTaxameter) {
                    feldOhneTaxameter.addEventListener(eventName, updateSchichtzielStatus);
                }
            });

            updateSchichtzielStatus();
        }

        // Overlay-Belohnung beim Absenden
        const form = document.getElementById('umsatzForm');

        if (form && overlaySub && rewardIcon && zielWert > 0) {
            form.addEventListener('submit', function () {
                const summe = parseEuro(feldTaxameter) + parseEuro(feldOhneTaxameter);

                if (summe <= 0) {
                    overlaySub.textContent = '';
                    rewardIcon.textContent = '';
                    rewardIcon.classList.remove('glow-gold', 'glow-diamond');
                    return;
                }

                const prozent = (summe / zielWert) * 100;

                rewardIcon.classList.remove('glow-gold', 'glow-diamond');

                if (prozent < 100) {
                    overlaySub.textContent = randomText(texteUnterZiel);
                    rewardIcon.textContent = "";
                } else if (prozent >= 100 && prozent < 120) {
                    overlaySub.textContent = randomText(texteKrone);
                    rewardIcon.textContent = "👑";
                    rewardIcon.classList.add('glow-gold');
                } else {
                    overlaySub.textContent = randomText(texteDiamant);
                    rewardIcon.textContent = "💎";
                    rewardIcon.classList.add('glow-diamond');
                }
            });
        }
    });
    </script>
</body>
</html>
