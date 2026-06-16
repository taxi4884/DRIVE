<?php
require_once '../../includes/head.php';

if ($_SESSION['user_role'] !== 'fahrer') {
    header('Location: ../index.php');
    exit();
}

$fahrer_id = (int)($_SESSION['user_id'] ?? 0);
$recherche_flag = 0;
$standardSchichtziel = 0.0;
$fahrerPersonalnummer = null;
$fahrerNummer = null;
$fahrerFmsAlias = null;
$fahrerCompanyId = null;
$fahrerCompanyName = null;

try {
    $stmtFahrer = $pdo->prepare("\n        SELECT 
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
    $stmtFahrer->execute(['fahrer_id' => $fahrer_id]);
    $rowFahrer = $stmtFahrer->fetch(PDO::FETCH_ASSOC);

    if (!$rowFahrer) {
        throw new RuntimeException('Fahrer nicht gefunden.');
    }

    $abrechnungsart = strtolower((string)($rowFahrer['abrechnungsart'] ?? 'alt'));
    if ($abrechnungsart !== 'neu') {
        header('Location: umsatz_erfassen.php');
        exit();
    }

    $recherche_flag = (int)$rowFahrer['recherche'];
    $standardSchichtziel = (float)$rowFahrer['standard_schichtziel'];
    $fahrerPersonalnummer = $rowFahrer['personalnummer'] ?? null;
    $fahrerNummer = $rowFahrer['fahrernummer'] ?? null;
    $fahrerFmsAlias = $rowFahrer['fms_alias'] ?? null;
    $fahrerCompanyId = isset($rowFahrer['company_id']) ? (int)$rowFahrer['company_id'] : null;
    if ($fahrerCompanyId) {
        try {
            $stmtCompany = $pdo->prepare('SELECT name FROM companies WHERE id = ? LIMIT 1');
            $stmtCompany->execute([$fahrerCompanyId]);
            $fahrerCompanyName = $stmtCompany->fetchColumn() ?: null;
        } catch (Throwable $e) {
            $fahrerCompanyName = null;
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Fehler beim Laden der Fahrerdaten.';
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

if (!function_exists('driverFormatGermanDateOrOriginal')) {
    function driverFormatGermanDateOrOriginal(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($value))->format('d.m.Y');
        } catch (Exception $e) {
            return (string)$value;
        }
    }
}

if (!function_exists('driverParseDateTimeLoose')) {
    function driverParseDateTimeLoose($input): ?DateTimeImmutable
    {
        if ($input === null || $input === '') {
            return null;
        }
        if ($input instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($input);
        }
        $value = trim((string)$input);
        if ($value === '') return null;

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            DateTimeInterface::ATOM,
        ];

        $berlinTz = new DateTimeZone('Europe/Berlin');
        foreach ($formats as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $value, $berlinTz);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->setTimezone($berlinTz);
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return (new DateTimeImmutable('@' . $ts))->setTimezone($berlinTz);
    }
}

if (!function_exists('driverApiFetchNeuFahrten')) {
    function driverApiFetchNeuFahrten(string $personalnummer, string $dateYmd, ?int $companyId = null, ?string $companyName = null): array
    {
        $tokenUrl = driverApiEnvByCompany('TAXIDATEN_TOKEN_URL', $companyId, $companyName, 'https://extern.taxidaten.com/token');
        $odataBase = rtrim((string)driverApiEnvByCompany('TAXIDATEN_ODATA_BASE', $companyId, $companyName, 'https://extern.taxidaten.com/odata'), '/');
        $apiUser = driverApiEnvByCompany('TAXIDATEN_API_USERNAME', $companyId, $companyName);
        $apiPass = driverApiEnvByCompany('TAXIDATEN_API_PASSWORD', $companyId, $companyName);
        $externalUser = driverApiEnvByCompany('TAXIDATEN_EXTERNAL_USER', $companyId, $companyName, 'VGhvbWFzQnVlaG5lcnQ0ODg0');

        if (!$apiUser || !$apiPass) {
            throw new RuntimeException('TSE-API Zugangsdaten fehlen (TAXIDATEN_API_USERNAME / TAXIDATEN_API_PASSWORD).');
        }

        if (!$externalUser) {
            $externalUser = 'VGhvbWFzQnVlaG5lcnQ0ODg0';
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

        $apiHeaders = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'ExternalUser: ' . $externalUser,
        ];

        // Schicht für das gewählte Datum suchen und e_link ermitteln
        $selectedELink = null;
        $selectedShiftStart = null;
        $selectedShiftEnd = null;
        $selectedShiftOpen = false;
        $schichtenUrl = $odataBase . '/schichten?%24orderby=id%20desc&%24top=200&%24filter=' . rawurlencode("persnr eq '{$personalnummer}'");
        $schichtenCh = curl_init($schichtenUrl);
        curl_setopt_array($schichtenCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $apiHeaders,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $schichtenResp = curl_exec($schichtenCh);
        $schichtenHttp = (int)curl_getinfo($schichtenCh, CURLINFO_HTTP_CODE);
        curl_close($schichtenCh);

        if ($schichtenResp !== false && $schichtenHttp >= 200 && $schichtenHttp < 300) {
            $schichtenJson = json_decode((string)$schichtenResp, true);
            $schichtenRows = [];
            if (is_array($schichtenJson)) {
                if (isset($schichtenJson['value']) && is_array($schichtenJson['value'])) {
                    $schichtenRows = $schichtenJson['value'];
                } elseif (array_keys($schichtenJson) === range(0, count($schichtenJson) - 1)) {
                    $schichtenRows = $schichtenJson;
                }
            }

            // 1) versuche Schicht exakt für gewähltes Datum (dateYmd)
            // 2) ignoriere offensichtlich kaputte/zu kurze e_link-Werte
            $pickFromRows = function(array $rows, bool $requireDate) use ($dateYmd, &$selectedELink, &$selectedShiftStart, &$selectedShiftEnd, &$selectedShiftOpen): bool {
                foreach ($rows as $sRow) {
                    if (!is_array($sRow)) continue;

                    $eLink = null;
                    foreach (['e_link', 'elink', 'eLink', 'schichtlink', 'schicht_link'] as $lk) {
                        if (!empty($sRow[$lk])) {
                            $eLink = trim((string)$sRow[$lk]);
                            break;
                        }
                    }
                    if (!$eLink || mb_strlen($eLink) < 18) continue;

                    $shiftStart = null;
                    $shiftEnd = null;
                    $shiftDateYmd = null;
                    try {
                        $baseDate = !empty($sRow['datum']) ? new DateTimeImmutable((string)$sRow['datum']) : null;
                        $anDate = !empty($sRow['an_datum']) ? new DateTimeImmutable((string)$sRow['an_datum']) : $baseDate;
                        if ($baseDate) {
                            $shiftDateYmd = $baseDate->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d');
                        }
                        if ($baseDate && !empty($sRow['abfahrt'])) {
                            $parts = explode(':', (string)$sRow['abfahrt']);
                            $h = (int)($parts[0] ?? 0);
                            $m = (int)($parts[1] ?? 0);
                            $shiftStart = $baseDate->setTime($h, $m, 0);
                        }
                        $hasAnkunft = !empty($sRow['ankunft']);
                        if ($anDate && $hasAnkunft) {
                            $parts = explode(':', (string)$sRow['ankunft']);
                            $h = (int)($parts[0] ?? 0);
                            $m = (int)($parts[1] ?? 0);
                            $shiftEnd = $anDate->setTime($h, $m, 59);
                        }

                        // Offene/noch laufende Schicht: Ende auf "jetzt" kappen
                        $nowBerlin = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
                        if (!$hasAnkunft || !($shiftEnd instanceof DateTimeInterface) || $shiftEnd > $nowBerlin) {
                            $selectedShiftOpen = true;
                            $shiftEnd = $nowBerlin;
                        }
                    } catch (Throwable $e) {
                        $shiftStart = null;
                        $shiftEnd = null;
                    }

                    if ($requireDate && $shiftDateYmd !== $dateYmd) {
                        continue;
                    }

                    $selectedELink = $eLink;
                    $selectedShiftStart = $shiftStart;
                    $selectedShiftEnd = $shiftEnd;
                    return true;
                }
                return false;
            };

            $picked = $pickFromRows($schichtenRows, true);
            if (!$picked) {
                $pickFromRows($schichtenRows, false);
            }
            unset($pickFromRows);
        }

        if (!$selectedELink) {
            throw new RuntimeException('Keine letzte Schicht mit e_link für diesen Fahrer gefunden.');
        }

        $fahrtenFilter = "persnr eq '{$personalnummer}' and e_link eq '{$selectedELink}'";
        $fahrtenUrl = $odataBase . '/fahrten?%24orderby=id%20desc&%24top=500&%24filter=' . rawurlencode($fahrtenFilter);
        $fahrtenCh = curl_init($fahrtenUrl);
        curl_setopt_array($fahrtenCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $apiHeaders,
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

        $dateCandidates = ['datum', 'date', 'zeit', 'zeitpunkt', 'startzeit', 'beginn', 'created_at', 'an_datum', 'abfahrt', 'ankunft', 'vermittlungszeitpunkt'];
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

            if ($selectedELink) {
                $rowELink = isset($row['e_link']) ? trim((string)$row['e_link']) : '';
                if ($rowELink !== $selectedELink) {
                    continue;
                }
            }

            $rideDate = null;
            $rideTime = null;

            // Primärzeit aus Schichtdatum + Abfahrtszeit bilden (Taxidaten liefert oft nur HH:MM in abfahrt/ankunft)
            $baseDateObj = null;
            if (!empty($row['s_datum'])) {
                $baseDateObj = driverParseDateTimeLoose((string)$row['s_datum']);
            }
            if (!$baseDateObj && !empty($row['datum'])) {
                $baseDateObj = driverParseDateTimeLoose((string)$row['datum']);
            }
            if ($baseDateObj instanceof DateTimeInterface) {
                $baseLocal = DateTimeImmutable::createFromInterface($baseDateObj)->setTimezone(new DateTimeZone('Europe/Berlin'));
                $timeSource = null;
                if (!empty($row['abfahrt'])) {
                    $timeSource = trim((string)$row['abfahrt']);
                } elseif (!empty($row['ankunft'])) {
                    $timeSource = trim((string)$row['ankunft']);
                }
                if ($timeSource && preg_match('/^(\d{1,2}):(\d{2})$/', $timeSource, $m)) {
                    $hh = max(0, min(23, (int)$m[1]));
                    $mm = max(0, min(59, (int)$m[2]));
                    $rideTime = $baseLocal->setTime($hh, $mm, 0);
                    $rideDate = $rideTime->format('Y-m-d');
                }
            }

            if (!$rideTime) {
                foreach ($dateCandidates as $candidate) {
                    if (!isset($row[$candidate]) || $row[$candidate] === null || $row[$candidate] === '') continue;
                    $dt = driverParseDateTimeLoose($row[$candidate]);
                    if ($dt) {
                        $rideDate = $dt->format('Y-m-d');
                        $rideTime = $dt;
                        break;
                    }
                }
            }
            if ($selectedShiftStart instanceof DateTimeInterface && $selectedShiftEnd instanceof DateTimeInterface && $rideTime instanceof DateTimeInterface) {
                if ($rideTime < $selectedShiftStart || $rideTime > $selectedShiftEnd) {
                    continue;
                }
            } elseif ($rideDate !== null && $rideDate !== $dateYmd) {
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
                'e_link' => $row['e_link'] ?? null,
                'von' => trim((string)($row['von'] ?? ($row['start'] ?? ($row['abholort'] ?? '')))),
                'nach' => trim((string)($row['nach'] ?? ($row['ziel'] ?? ''))),
                'zeit' => $rideTime ? $rideTime->format('Y-m-d H:i:s') : (string)($row['zeit'] ?? ($row['datum'] ?? ($row['startzeit'] ?? ''))),
                'zeit_obj' => $rideTime,
                'betrag' => $money,
                'raw' => $row,
            ];
        }

        usort($filtered, static function (array $a, array $b): int {
            $hasA = $a['zeit_obj'] instanceof DateTimeInterface;
            $hasB = $b['zeit_obj'] instanceof DateTimeInterface;
            if ($hasA && !$hasB) return -1;
            if (!$hasA && $hasB) return 1;
            if (!$hasA && !$hasB) return 0;
            return $a['zeit_obj']->getTimestamp() <=> $b['zeit_obj']->getTimestamp();
        });

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
            'shift_open' => $selectedShiftOpen,
            'shift_start' => $selectedShiftStart instanceof DateTimeInterface ? $selectedShiftStart->format('Y-m-d H:i:s') : null,
            'shift_end' => $selectedShiftEnd instanceof DateTimeInterface ? $selectedShiftEnd->format('Y-m-d H:i:s') : null,
        ];
    }
}

if (!function_exists('driverFetchFmsAuftraege')) {
    function driverFetchFmsAuftraege(string $fahrerDisplaynummer, string $dateYmd): array
    {
        $dtVon = DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateYmd . ' 00:00');
        $dtBis = DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateYmd . ' 23:59');
        if (!$dtVon || !$dtBis) {
            return [];
        }

        $base = 'https://4884gateway.de/fms';
        $query = [
            'funktion' => 'GETAUFTRAGLISTE',
            'DATUM_VON' => $dtVon->format('d.m.Y H:i'),
            'FAHRER_DISPLAYNUMMER' => $fahrerDisplaynummer,
            'AUFTRAGSTATUS' => 7,
            'DATUM_BIS' => $dtBis->format('d.m.Y H:i'),
        ];

        $requestUrl = $base . '?' . str_replace('+', '%20', http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: DRIVE-Fahrer-Portal/1.0',
            ],
        ]);
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('FMS-Gateway nicht erreichbar: ' . ($curlErr ?: ('HTTP ' . $status)));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('FMS-Gateway liefert kein gültiges JSON.');
        }

        $extractMoney = static function (array $row): ?float {
            $candidates = [
                'fahrpreis','preis','betrag','summe','brutto','netto','gesamtpreis','endpreis',
                'fahrpreisBrutto','fahrpreisNetto','gesamtbetrag','gesamtsumme'
            ];
            foreach ($candidates as $k) {
                if (!array_key_exists($k, $row)) continue;
                $v = $row[$k];
                if (is_numeric($v)) {
                    $f = (float)$v;
                    if ($f > 0) return round($f, 2);
                }
                if (is_string($v)) {
                    $n = str_replace([' ', ','], ['', '.'], $v);
                    if (is_numeric($n)) {
                        $f = (float)$n;
                        if ($f > 0) return round($f, 2);
                    }
                }
            }

            $stack = [$row];
            while (!empty($stack)) {
                $cur = array_pop($stack);
                foreach ($cur as $k => $v) {
                    if (is_array($v)) {
                        $stack[] = $v;
                        continue;
                    }
                    $ks = strtolower((string)$k);
                    if (!(str_contains($ks, 'preis') || str_contains($ks, 'betrag') || str_contains($ks, 'summe') || str_contains($ks, 'brutto'))) {
                        continue;
                    }
                    if (is_numeric($v)) {
                        $f = (float)$v;
                        if ($f > 0) return round($f, 2);
                    } elseif (is_string($v)) {
                        $n = str_replace([' ', ','], ['', '.'], $v);
                        if (is_numeric($n)) {
                            $f = (float)$n;
                            if ($f > 0) return round($f, 2);
                        }
                    }
                }
            }
            return null;
        };

        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;

            $dt = driverParseDateTimeLoose($row['datumDerFahrt'] ?? null);
            $vermitteltDt = driverParseDateTimeLoose($row['vermittlungszeitpunkt'] ?? null);
            $abfahrt = $row['abfahrt'] ?? [];
            $ziel = $row['ziel'] ?? [];
            $abfahrtText = trim(implode(', ', array_filter([
                trim((string)($abfahrt['kundeName'] ?? ($abfahrt['fahrtkundeName'] ?? ''))),
                trim((string)(($abfahrt['strasse'] ?? '') . ' ' . ($abfahrt['hausnummer'] ?? ''))),
                trim((string)($abfahrt['ort'] ?? '')),
            ])));
            $zielText = trim(implode(', ', array_filter([
                trim((string)($ziel['kundeName'] ?? ($ziel['fahrtkundeName'] ?? ''))),
                trim((string)(($ziel['strasse'] ?? '') . ' ' . ($ziel['hausnummer'] ?? ''))),
                trim((string)($ziel['ort'] ?? '')),
            ])));

            $auftragId = null;
            foreach (['auftragID', 'auftragId', 'id', 'tourNummer', 'fahrtNummer'] as $idField) {
                if (!empty($row[$idField])) {
                    $auftragId = (string)$row[$idField];
                    break;
                }
            }

            $fmsBetrag = $extractMoney($row);

            $items[] = [
                'auftrag_id' => $auftragId,
                'zeit_obj' => $dt,
                'vermittelt_obj' => $vermitteltDt,
                'zeit' => $dt ? $dt->format('Y-m-d H:i:s') : (string)($row['datumDerFahrt'] ?? ''),
                'abfahrt' => $abfahrtText,
                'ziel' => $zielText,
                'betrag' => $fmsBetrag,
                'raw' => $row,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $hasA = $a['zeit_obj'] instanceof DateTimeInterface;
            $hasB = $b['zeit_obj'] instanceof DateTimeInterface;
            if ($hasA && !$hasB) return -1;
            if (!$hasA && $hasB) return 1;
            if (!$hasA && !$hasB) return 0;
            return $a['zeit_obj']->getTimestamp() <=> $b['zeit_obj']->getTimestamp();
        });

        return $items;
    }
}

if (!function_exists('driverDetectDefaultKategorie')) {
    function driverDetectDefaultKategorie(array $fahrt): string
    {
        $markers = [];
        $raw = is_array($fahrt['raw'] ?? null) ? $fahrt['raw'] : [];
        foreach (['merkmale', 'merkmal', 'flags'] as $k) {
            if (!empty($raw[$k])) {
                if (is_array($raw[$k])) {
                    foreach ($raw[$k] as $m) {
                        $markers[] = strtoupper(trim((string)$m));
                    }
                } else {
                    $markers[] = strtoupper(trim((string)$raw[$k]));
                }
            }
        }

        $fms = is_array($fahrt['fms_match'] ?? null) ? $fahrt['fms_match'] : [];
        if (!empty($fms['merkmale']) && is_array($fms['merkmale'])) {
            foreach ($fms['merkmale'] as $m) {
                $markers[] = strtoupper(trim((string)$m));
            }
        }

        foreach ($markers as $m) {
            if (str_contains($m, 'KKBEX') || preg_match('/\bKKB\b/', $m)) {
                return 'krankenfahrt';
            }
        }
        foreach ($markers as $m) {
            if (str_contains($m, 'RF')) {
                return 'rechnung';
            }
        }
        foreach ($markers as $m) {
            if (str_contains($m, 'ALI')) {
                return 'alita';
            }
        }

        return 'bar';
    }
}

if (!function_exists('driverNormalizeAddr')) {
    function driverNormalizeAddr(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/', ' ', $s ?? '');
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', '', $s ?? '');
        return trim((string)$s);
    }
}

if (!function_exists('driverAppendPauschalFromFms')) {
    function driverAppendPauschalFromFms(array $fahrten, array $fmsRows): array
    {
        $existingSignatures = [];
        $existingAuftragIds = [];

        $usedFmsIndexes = [];
        foreach ($fahrten as $fahrt) {
            $existingAuftragId = trim((string)($fahrt['fms_match']['auftrag_id'] ?? ''));
            if ($existingAuftragId !== '') {
                $existingAuftragIds[$existingAuftragId] = true;
            }
            if (isset($fahrt['fms_match']['fms_index']) && is_numeric($fahrt['fms_match']['fms_index'])) {
                $usedFmsIndexes[(int)$fahrt['fms_match']['fms_index']] = true;
            }

            $sigParts = [
                trim((string)($fahrt['von'] ?? '')),
                trim((string)($fahrt['nach'] ?? '')),
                ($fahrt['zeit_obj'] instanceof DateTimeInterface) ? $fahrt['zeit_obj']->format('Y-m-d H:i') : trim((string)($fahrt['zeit'] ?? '')),
                $existingAuftragId,
            ];
            $existingSignatures[] = implode('|', $sigParts);
        }

        foreach ($fmsRows as $fmsIdx => $fms) {
            if (isset($usedFmsIndexes[(int)$fmsIdx])) {
                continue;
            }
            $fmsAuftragId = trim((string)($fms['auftrag_id'] ?? ''));
            if ($fmsAuftragId !== '' && isset($existingAuftragIds[$fmsAuftragId])) {
                // bereits gematcht -> nicht als zusätzliche Pauschalfahrt anhängen
                continue;
            }

            $sigParts = [
                trim((string)($fms['abfahrt'] ?? '')),
                trim((string)($fms['ziel'] ?? '')),
                ($fms['zeit_obj'] instanceof DateTimeInterface) ? $fms['zeit_obj']->format('Y-m-d H:i') : trim((string)($fms['zeit'] ?? '')),
                $fmsAuftragId,
            ];
            $sig = implode('|', $sigParts);
            if (in_array($sig, $existingSignatures, true)) {
                continue;
            }

            // Dedupe/Merge-Guard: ähnliche Route -> bevorzugt in bestehende Taxidaten-Fahrt mergen statt doppelt anhängen
            $fmsVonNorm = driverNormalizeAddr((string)($fms['abfahrt'] ?? ''));
            $fmsNachNorm = driverNormalizeAddr((string)($fms['ziel'] ?? ''));
            $fmsTs = ($fms['zeit_obj'] instanceof DateTimeInterface) ? $fms['zeit_obj']->getTimestamp() : null;
            $looksLikeDuplicate = false;
            $mergeIdx = null;
            foreach ($fahrten as $existingIdx => $existingRide) {
                $exVon = driverNormalizeAddr((string)($existingRide['von'] ?? ''));
                $exNach = driverNormalizeAddr((string)($existingRide['nach'] ?? ''));
                $exTs = ($existingRide['zeit_obj'] instanceof DateTimeInterface) ? $existingRide['zeit_obj']->getTimestamp() : null;
                $exAmount = (float)($existingRide['betrag'] ?? 0.0);

                $routeSimilar = ($fmsVonNorm !== '' && $fmsNachNorm !== '' && $exVon !== '' && $exNach !== '')
                    && (
                        str_contains($exVon, $fmsVonNorm) || str_contains($fmsVonNorm, $exVon)
                    )
                    && (
                        str_contains($exNach, $fmsNachNorm) || str_contains($fmsNachNorm, $exNach)
                    );

                $timeNear = ($fmsTs !== null && $exTs !== null && abs($fmsTs - $exTs) <= (25 * 60));
                $missingRideTime = ($exTs === null);

                // harte Regel 1: Taxidaten-Betrag vorhanden + zeitlich nah -> mergen, auch wenn Route leer/abweichend
                if ($exAmount > 0 && $timeNear) {
                    $looksLikeDuplicate = true;
                    $mergeIdx = $existingIdx;
                    break;
                }

                // harte Regel 2: gleiche Route + vorhandener Betrag -> mergen
                if ($routeSimilar && $exAmount > 0) {
                    $looksLikeDuplicate = true;
                    $mergeIdx = $existingIdx;
                    break;
                }

                if ($routeSimilar && ($timeNear || $missingRideTime)) {
                    $looksLikeDuplicate = true;
                    $mergeIdx = $existingIdx;
                    break;
                }
            }
            if ($looksLikeDuplicate) {
                if ($mergeIdx !== null) {
                    if (!($fahrten[$mergeIdx]['zeit_obj'] instanceof DateTimeInterface) && ($fms['zeit_obj'] instanceof DateTimeInterface)) {
                        $fahrten[$mergeIdx]['zeit_obj'] = $fms['zeit_obj'];
                        $fahrten[$mergeIdx]['zeit'] = $fms['zeit'] ?? $fahrten[$mergeIdx]['zeit'];
                    }
                    if (trim((string)($fahrten[$mergeIdx]['von'] ?? '')) === '' && trim((string)($fms['abfahrt'] ?? '')) !== '') {
                        $fahrten[$mergeIdx]['von'] = (string)$fms['abfahrt'];
                    }
                    if (trim((string)($fahrten[$mergeIdx]['nach'] ?? '')) === '' && trim((string)($fms['ziel'] ?? '')) !== '') {
                        $fahrten[$mergeIdx]['nach'] = (string)$fms['ziel'];
                    }
                    $fahrten[$mergeIdx]['fms_match'] = [
                        'auftrag_id' => $fms['auftrag_id'] ?? null,
                        'delta_sec' => null,
                        'zeit' => $fms['zeit'] ?? null,
                        'merkmale' => is_array($fms['raw']['merkmale'] ?? null) ? $fms['raw']['merkmale'] : [],
                    ];
                }
                continue;
            }

            $fahrten[] = [
                'id' => null,
                'e_link' => null,
                'von' => (string)($fms['abfahrt'] ?? ''),
                'nach' => (string)($fms['ziel'] ?? ''),
                'zeit' => (string)($fms['zeit'] ?? ''),
                'zeit_obj' => $fms['zeit_obj'] ?? null,
                'betrag' => isset($fms['betrag']) && is_numeric($fms['betrag']) ? (float)$fms['betrag'] : 0.0,
                'raw' => [
                    'merkmale' => is_array($fms['raw']['merkmale'] ?? null) ? $fms['raw']['merkmale'] : [],
                    'pauschalfahrt' => true,
                    'source' => 'fms_only',
                ],
                'fms_match' => [
                    'auftrag_id' => $fms['auftrag_id'] ?? null,
                    'delta_sec' => null,
                    'pauschalfahrt' => true,
                    'merkmale' => is_array($fms['raw']['merkmale'] ?? null) ? $fms['raw']['merkmale'] : [],
                ],
            ];
            $existingSignatures[] = $sig;
            $usedFmsIndexes[(int)$fmsIdx] = true;
            if ($fmsAuftragId !== '') {
                $existingAuftragIds[$fmsAuftragId] = true;
            }
        }

        usort($fahrten, static function (array $a, array $b): int {
            $hasA = $a['zeit_obj'] instanceof DateTimeInterface;
            $hasB = $b['zeit_obj'] instanceof DateTimeInterface;
            if ($hasA && !$hasB) return -1;
            if (!$hasA && $hasB) return 1;
            if (!$hasA && !$hasB) return 0;
            return $a['zeit_obj']->getTimestamp() <=> $b['zeit_obj']->getTimestamp();
        });

        return $fahrten;
    }
}

if (!function_exists('driverReconcilePauschalAmounts')) {
    function driverReconcilePauschalAmounts(array $fahrten): array
    {
        $rows = array_values($fahrten);
        $usedCandidates = [];
        $remove = [];

        for ($i = 0; $i < count($rows); $i++) {
            $isPauschal = !empty($rows[$i]['fms_match']['pauschalfahrt']);
            $amount = (float)($rows[$i]['betrag'] ?? 0.0);
            if (!$isPauschal || $amount > 0) continue;

            $pTs = ($rows[$i]['zeit_obj'] instanceof DateTimeInterface) ? $rows[$i]['zeit_obj']->getTimestamp() : null;
            $pVon = driverNormalizeAddr((string)($rows[$i]['von'] ?? ''));
            $pNach = driverNormalizeAddr((string)($rows[$i]['nach'] ?? ''));

            $bestIdx = null;
            $bestDelta = PHP_INT_MAX;
            for ($j = 0; $j < count($rows); $j++) {
                if ($i === $j || isset($usedCandidates[$j])) continue;

                $candAmount = (float)($rows[$j]['betrag'] ?? 0.0);
                if ($candAmount <= 0) continue;
                if (!empty($rows[$j]['fms_match']['pauschalfahrt'])) continue;

                $cTs = ($rows[$j]['zeit_obj'] instanceof DateTimeInterface) ? $rows[$j]['zeit_obj']->getTimestamp() : null;
                if ($pTs === null || $cTs === null) continue;
                $delta = abs($pTs - $cTs);
                if ($delta > (12 * 60)) continue;

                $cVon = driverNormalizeAddr((string)($rows[$j]['von'] ?? ''));
                $cNach = driverNormalizeAddr((string)($rows[$j]['nach'] ?? ''));
                $routeSimilar = ($pVon !== '' && $pNach !== '' && $cVon !== '' && $cNach !== ''
                    && (str_contains($pVon, $cVon) || str_contains($cVon, $pVon))
                    && (str_contains($pNach, $cNach) || str_contains($cNach, $pNach)));
                $routeWeak = ($cVon === '' || $cNach === '');

                if (!($routeSimilar || $routeWeak)) continue;

                if ($delta < $bestDelta) {
                    $bestDelta = $delta;
                    $bestIdx = $j;
                }
            }

            if ($bestIdx !== null) {
                $rows[$i]['betrag'] = (float)$rows[$bestIdx]['betrag'];
                if (!($rows[$i]['zeit_obj'] instanceof DateTimeInterface) && ($rows[$bestIdx]['zeit_obj'] instanceof DateTimeInterface)) {
                    $rows[$i]['zeit_obj'] = $rows[$bestIdx]['zeit_obj'];
                    $rows[$i]['zeit'] = $rows[$bestIdx]['zeit'] ?? $rows[$i]['zeit'];
                }
                $rows[$i]['fms_match']['pauschalfahrt'] = false;
                $usedCandidates[$bestIdx] = true;
                $remove[$bestIdx] = true;
            }
        }

        $out = [];
        foreach ($rows as $idx => $row) {
            if (!isset($remove[$idx])) $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('driverCollapseNearDuplicates')) {
    function driverCollapseNearDuplicates(array $fahrten): array
    {
        $keep = array_values($fahrten);
        $toRemove = [];

        for ($i = 0; $i < count($keep); $i++) {
            if (isset($toRemove[$i])) continue;
            for ($j = $i + 1; $j < count($keep); $j++) {
                if (isset($toRemove[$j])) continue;

                $a = $keep[$i];
                $b = $keep[$j];
                $aPauschal = !empty($a['fms_match']['pauschalfahrt']);
                $bPauschal = !empty($b['fms_match']['pauschalfahrt']);
                if (!$aPauschal && !$bPauschal) {
                    continue;
                }

                $aAmt = (float)($a['betrag'] ?? 0.0);
                $bAmt = (float)($b['betrag'] ?? 0.0);
                $aTs = ($a['zeit_obj'] instanceof DateTimeInterface) ? $a['zeit_obj']->getTimestamp() : null;
                $bTs = ($b['zeit_obj'] instanceof DateTimeInterface) ? $b['zeit_obj']->getTimestamp() : null;
                $timeNear = ($aTs !== null && $bTs !== null && abs($aTs - $bTs) <= (10 * 60));

                $aVon = driverNormalizeAddr((string)($a['von'] ?? ''));
                $aNach = driverNormalizeAddr((string)($a['nach'] ?? ''));
                $bVon = driverNormalizeAddr((string)($b['von'] ?? ''));
                $bNach = driverNormalizeAddr((string)($b['nach'] ?? ''));
                $routeSimilar = ($aVon !== '' && $aNach !== '' && $bVon !== '' && $bNach !== '')
                    && ((str_contains($aVon, $bVon) || str_contains($bVon, $aVon))
                    && (str_contains($aNach, $bNach) || str_contains($bNach, $aNach)));

                if (!($timeNear || $routeSimilar)) {
                    continue;
                }

                // Merge-Regel: behalten den Datensatz mit Betrag > 0 (oder mit Zeit), löschen den anderen.
                $keepIdx = $i;
                $dropIdx = $j;

                if ($bAmt > $aAmt) {
                    $keepIdx = $j;
                    $dropIdx = $i;
                } elseif ($aAmt === $bAmt && $aTs === null && $bTs !== null) {
                    $keepIdx = $j;
                    $dropIdx = $i;
                }

                // Betrag übernehmen falls nötig
                if ((float)($keep[$keepIdx]['betrag'] ?? 0.0) <= 0 && (float)($keep[$dropIdx]['betrag'] ?? 0.0) > 0) {
                    $keep[$keepIdx]['betrag'] = (float)$keep[$dropIdx]['betrag'];
                }

                // Zeit übernehmen falls nötig
                if (!($keep[$keepIdx]['zeit_obj'] instanceof DateTimeInterface) && ($keep[$dropIdx]['zeit_obj'] instanceof DateTimeInterface)) {
                    $keep[$keepIdx]['zeit_obj'] = $keep[$dropIdx]['zeit_obj'];
                    $keep[$keepIdx]['zeit'] = $keep[$dropIdx]['zeit'] ?? $keep[$keepIdx]['zeit'];
                }

                // Wenn jetzt Betrag vorhanden, nicht mehr als Pauschalfahrt labeln
                if ((float)($keep[$keepIdx]['betrag'] ?? 0.0) > 0) {
                    $keep[$keepIdx]['fms_match']['pauschalfahrt'] = false;
                }

                $toRemove[$dropIdx] = true;
            }
        }

        $out = [];
        foreach ($keep as $idx => $row) {
            if (!isset($toRemove[$idx])) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('driverEnrichFahrtenWithFms')) {
    function driverEnrichFahrtenWithFms(array $fahrten, array $fmsRows): array
    {
        $used = [];
        $matchedCount = 0;
        $toleranceSec = 8 * 60; // normales Matching (inkl. 00:01↔00:07 Fälle)
        $extendedToleranceSec = 30 * 60; // Problemfälle (z. B. Sofortauftrag ohne Preis): Vermittlung deutlich vor Taxameterstart
        foreach ($fahrten as $idx => $fahrt) {
            $bestIdx = null;
            $bestDelta = PHP_INT_MAX;
            $rideTs = ($fahrt['zeit_obj'] instanceof DateTimeInterface) ? $fahrt['zeit_obj']->getTimestamp() : null;
            $rideLink = isset($fahrt['e_link']) ? trim((string)$fahrt['e_link']) : '';

            foreach ($fmsRows as $fmsIdx => $fms) {
                if (isset($used[$fmsIdx])) continue;

                $fmsId = trim((string)($fms['auftrag_id'] ?? ''));
                if ($rideLink !== '' && $fmsId !== '' && strcasecmp($rideLink, $fmsId) === 0) {
                    $bestIdx = $fmsIdx;
                    $bestDelta = 0;
                    break;
                }

                if ($rideTs === null) continue;

                $candidates = [];
                if ($fms['zeit_obj'] instanceof DateTimeInterface) {
                    $candidates[] = $fms['zeit_obj']->getTimestamp();
                }
                if (($fms['vermittelt_obj'] ?? null) instanceof DateTimeInterface) {
                    $candidates[] = $fms['vermittelt_obj']->getTimestamp();
                }
                if (empty($candidates)) continue;

                foreach ($candidates as $fmsTs) {
                    $delta = abs($rideTs - $fmsTs);
                    if ($delta <= $toleranceSec && $delta < $bestDelta) {
                        $bestDelta = $delta;
                        $bestIdx = $fmsIdx;
                    }
                }
            }

            // 2. Pass für Problemfälle: erweitertes Zeitfenster, aber nur wenn Taxidaten-Zeile noch wenig Informationen hat
            if ($bestIdx === null && $rideTs !== null) {
                $rideHasRoute = trim((string)($fahrt['von'] ?? '')) !== '' || trim((string)($fahrt['nach'] ?? '')) !== '';
                $rideAmount = (float)($fahrt['betrag'] ?? 0);
                $rideHasAmount = $rideAmount > 0.0;

                // Erweiterte Toleranz nicht nur bei fehlender Route,
                // sondern auch bei Sofortaufträgen ohne Preis in Taxidaten.
                if (!$rideHasRoute || !$rideHasAmount) {
                    foreach ($fmsRows as $fmsIdx => $fms) {
                        if (isset($used[$fmsIdx])) continue;

                        $candidates = [];
                        if ($fms['zeit_obj'] instanceof DateTimeInterface) {
                            $candidates[] = $fms['zeit_obj']->getTimestamp();
                        }
                        if (($fms['vermittelt_obj'] ?? null) instanceof DateTimeInterface) {
                            $candidates[] = $fms['vermittelt_obj']->getTimestamp();
                        }
                        if (empty($candidates)) continue;

                        foreach ($candidates as $fmsTs) {
                            $delta = abs($rideTs - $fmsTs);
                            if ($delta <= $extendedToleranceSec && $delta < $bestDelta) {
                                $bestDelta = $delta;
                                $bestIdx = $fmsIdx;
                            }
                        }
                    }
                }
            }

            if ($bestIdx !== null) {
                $used[$bestIdx] = true;
                $matchedCount++;
                $match = $fmsRows[$bestIdx];
                if (trim((string)($match['abfahrt'] ?? '')) !== '') {
                    $fahrten[$idx]['von'] = $match['abfahrt'];
                }
                if (trim((string)($match['ziel'] ?? '')) !== '') {
                    $fahrten[$idx]['nach'] = $match['ziel'];
                }
                if (!($fahrt['zeit_obj'] instanceof DateTimeInterface) && ($match['zeit_obj'] instanceof DateTimeInterface)) {
                    $fahrten[$idx]['zeit_obj'] = $match['zeit_obj'];
                    $fahrten[$idx]['zeit'] = $match['zeit'];
                }
                $fahrten[$idx]['fms_match'] = [
                    'auftrag_id' => $match['auftrag_id'] ?? null,
                    'delta_sec' => $bestDelta,
                    'zeit' => $match['zeit'] ?? null,
                    'fms_index' => $bestIdx,
                    'fms_betrag' => isset($match['betrag']) && is_numeric($match['betrag']) ? (float)$match['betrag'] : null,
                    'merkmale' => is_array($match['raw']['merkmale'] ?? null) ? $match['raw']['merkmale'] : [],
                ];

                if (((float)($fahrten[$idx]['betrag'] ?? 0)) <= 0 && isset($match['betrag']) && is_numeric($match['betrag']) && (float)$match['betrag'] > 0) {
                    $fahrten[$idx]['betrag'] = round((float)$match['betrag'], 2);
                }
            }
        }

        return $fahrten;
    }
}

// Offene Schichten wie bisher
$stmt = $pdo->prepare("\n    SELECT DISTINCT DATE(sfa.anmeldung) AS offenes_datum
    FROM sync_fahreranmeldung sfa
    JOIN Fahrer f 
        ON sfa.fahrer = f.Fahrernummer OR sfa.fahrer = f.fms_alias
    WHERE f.FahrerID = :fahrer_id
      AND DATE(sfa.anmeldung) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND DATE(sfa.anmeldung) < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
      AND DATE(sfa.anmeldung) NOT IN (
          SELECT DATE(Datum) FROM Umsatz WHERE FahrerID = :fahrer_id
      )
    ORDER BY offenes_datum DESC
");
$stmt->execute(['fahrer_id' => $fahrer_id]);
$offene_daten = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$verfuegbare_daten = $offene_daten;

$datum = isset($_POST['datum']) ? trim((string)$_POST['datum']) : (isset($_GET['datum']) ? trim((string)$_GET['datum']) : '');
if ($datum === '' && !empty($offene_daten)) {
    $datum = (string)$offene_daten[0];
} elseif ($datum === '') {
    $jetzt = new DateTimeImmutable('now');
    $datum = ((int)$jetzt->format('H') < 6 ? $jetzt->sub(new DateInterval('P1D')) : $jetzt)->format('Y-m-d');
}

if (!in_array($datum, $verfuegbare_daten, true)) {
    array_unshift($verfuegbare_daten, $datum);
    $verfuegbare_daten = array_values(array_unique($verfuegbare_daten));
}

$errorMessages = [];
$fieldErrors = [];
$apiWarnings = [];

$expenses = [
    'tanken_waschen' => isset($_POST['tanken_waschen']) ? trim((string)$_POST['tanken_waschen']) : '',
    'sonstige_ausgaben' => isset($_POST['sonstige_ausgaben']) ? trim((string)$_POST['sonstige_ausgaben']) : '',
    'notiz' => isset($_POST['notiz']) ? trim((string)$_POST['notiz']) : '',
];

$fahrten = [];
$selectedByRideFromDb = [];
$loadedFromNeuSchema = false;

// Optional: gespeicherte Detaildaten nur bei explizitem load_saved=1 verwenden, sonst immer live aus API/FMS laden.
try {
    $useSaved = isset($_GET['load_saved']) && (string)$_GET['load_saved'] === '1';
    $hasNeuHead = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'driver_abrechnung_neu'")->fetchColumn();
    $hasNeuDetails = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'driver_abrechnung_neu_fahrten'")->fetchColumn();

    if ($useSaved && $hasNeuHead && $hasNeuDetails) {
        $stmtHead = $pdo->prepare('SELECT * FROM driver_abrechnung_neu WHERE fahrer_id = ? AND datum = ? LIMIT 1');
        $stmtHead->execute([$fahrer_id, $datum]);
        $headRow = $stmtHead->fetch(PDO::FETCH_ASSOC);

        if ($headRow) {
            $loadedFromNeuSchema = true;
            $expenses['tanken_waschen'] = $expenses['tanken_waschen'] !== '' ? $expenses['tanken_waschen'] : (string)$headRow['tanken_waschen'];
            $expenses['sonstige_ausgaben'] = $expenses['sonstige_ausgaben'] !== '' ? $expenses['sonstige_ausgaben'] : (string)$headRow['sonstige_ausgaben'];
            $expenses['notiz'] = $expenses['notiz'] !== '' ? $expenses['notiz'] : (string)($headRow['notiz'] ?? '');

            $stmtDetails = $pdo->prepare('SELECT * FROM driver_abrechnung_neu_fahrten WHERE abrechnung_neu_id = ? ORDER BY ride_idx ASC, id ASC');
            $stmtDetails->execute([(int)$headRow['id']]);
            $detailRows = $stmtDetails->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($detailRows as $idx => $d) {
                $zeitObj = !empty($d['zeitpunkt']) ? driverParseDateTimeLoose((string)$d['zeitpunkt']) : null;
                $fahrten[] = [
                    'id' => $d['taxidaten_id'] ?? null,
                    'e_link' => $d['taxidaten_e_link'] ?? null,
                    'von' => $d['von_text'] ?? '',
                    'nach' => $d['nach_text'] ?? '',
                    'zeit' => $d['zeitpunkt'] ?? '',
                    'zeit_obj' => $zeitObj,
                    'betrag' => isset($d['brutto']) ? (float)$d['brutto'] : 0.0,
                    'fms_match' => ['auftrag_id' => $d['fms_auftrag_id'] ?? null],
                    'raw' => json_decode((string)($d['raw_snapshot_json'] ?? '[]'), true) ?: [],
                ];

                $selectedByRideFromDb[$idx] = [
                    'kategorie' => (string)($d['kategorie'] ?? 'bar'),
                    'mwst' => (int)($d['mwst_satz'] ?? 7),
                    'betrag' => isset($d['brutto']) ? round((float)$d['brutto'], 2) : 0.0,
                ];
            }
        }
    }
} catch (Throwable $t) {
    // nicht blockierend im MVP
}

$referenceNumber = $fahrerPersonalnummer ?: ($fahrerNummer ?: $fahrerFmsAlias);
$displayNummer = $fahrerNummer ?: $fahrerFmsAlias;

if (!$loadedFromNeuSchema) {
    if (!$referenceNumber) {
        $apiWarnings[] = 'Für Abrechnungsart "neu" fehlt Personalnummer/Fahrernummer/FMS-Alias.';
    } else {
        try {
            $neuData = driverApiFetchNeuFahrten((string)$referenceNumber, $datum, $fahrerCompanyId, $fahrerCompanyName);
            $fahrten = $neuData['fahrten'] ?? [];
            if (!empty($neuData['shift_open'])) {
                $apiWarnings[] = 'Hinweis: Diese Schicht läuft noch – es werden nur Fahrten bis zum aktuellen Zeitpunkt berücksichtigt.';
            }
        } catch (Throwable $t) {
            $apiWarnings[] = 'Taxidaten nicht verfügbar: ' . $t->getMessage();
            $fahrten = [];
        }

        if ($displayNummer) {
            try {
                $fmsRows = driverFetchFmsAuftraege((string)$displayNummer, $datum);
                if (!empty($fmsRows)) {
                    if (!empty($fahrten)) {
                        $fahrten = driverEnrichFahrtenWithFms($fahrten, $fmsRows);
                    }
                    $fahrten = driverAppendPauschalFromFms($fahrten, $fmsRows);
                    $fahrten = driverCollapseNearDuplicates($fahrten);
                    $fahrten = driverReconcilePauschalAmounts($fahrten);
                }
            } catch (Throwable $t) {
                $apiWarnings[] = 'FMS-Anreicherung aktuell nicht verfügbar: ' . $t->getMessage();
            }
        }
    }
}

$categoryKeys = ['bar', 'karte', 'rechnung', 'krankenfahrt', 'gutschein', 'alita'];
$totals = [
    'bar' => 0.0,
    'karte' => 0.0,
    'rechnung' => 0.0,
    'krankenfahrt' => 0.0,
    'gutschein' => 0.0,
    'alita' => 0.0,
    'summe' => 0.0,
];

$selectedByRide = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedRides = $_POST['fahrten'] ?? [];
    if (!is_array($postedRides)) {
        $postedRides = [];
    }

    // Manuelle Fahrten aus POST ergänzen (falls nicht aus Taxidaten/FMS stammen)
    foreach ($postedRides as $rideKey => $ridePost) {
        if (!is_array($ridePost)) {
            continue;
        }
        $isExisting = is_int($rideKey) || (ctype_digit((string)$rideKey) && isset($fahrten[(int)$rideKey]));
        if ($isExisting) {
            continue;
        }

        $manualFlag = (string)($ridePost['manual'] ?? '0');
        if ($manualFlag !== '1') {
            continue;
        }

        $manualZeitRaw = trim((string)($ridePost['zeit'] ?? ''));
        $manualVon = trim((string)($ridePost['von'] ?? ''));
        $manualNach = trim((string)($ridePost['nach'] ?? ''));
        $manualBetrag = str_replace(',', '.', trim((string)($ridePost['betrag'] ?? '0')));
        $manualBetragNum = is_numeric($manualBetrag) ? max(0.0, round((float)$manualBetrag, 2)) : 0.0;

        $manualZeitObj = null;
        if ($manualZeitRaw !== '') {
            $manualZeitObj = driverParseDateTimeLoose($datum . ' ' . $manualZeitRaw) ?: driverParseDateTimeLoose($manualZeitRaw);
        }

        $fahrten[] = [
            'id' => null,
            'e_link' => null,
            'von' => $manualVon,
            'nach' => $manualNach,
            'zeit' => $manualZeitObj ? $manualZeitObj->format('Y-m-d H:i:s') : $manualZeitRaw,
            'zeit_obj' => $manualZeitObj,
            'betrag' => $manualBetragNum,
            'raw' => ['manual' => true],
            'fms_match' => ['manual' => true],
        ];
    }

    foreach ($fahrten as $idx => $fahrt) {
        $betrag = isset($fahrt['betrag']) && is_numeric($fahrt['betrag']) ? round((float)$fahrt['betrag'], 2) : 0.0;

        if (isset($postedRides[$idx]) && is_array($postedRides[$idx])) {
            $betragRaw = str_replace(',', '.', (string)($postedRides[$idx]['betrag'] ?? ''));
            if ($betragRaw !== '' && is_numeric($betragRaw)) {
                $betrag = max(0.0, round((float)$betragRaw, 2));
            }
        }

        $cat = isset($selectedByRideFromDb[$idx]['kategorie']) ? (string)$selectedByRideFromDb[$idx]['kategorie'] : driverDetectDefaultKategorie($fahrt);
        if (!in_array($cat, $categoryKeys, true)) {
            $cat = 'bar';
        }
        $mwst = isset($selectedByRideFromDb[$idx]['mwst']) ? (int)$selectedByRideFromDb[$idx]['mwst'] : 7;
        $karteBetrag = isset($selectedByRideFromDb[$idx]['karte_betrag']) ? (float)$selectedByRideFromDb[$idx]['karte_betrag'] : $betrag;
        $zuzahlung = round(min(10.0, max(5.0, $betrag * 0.10)), 2);
        if ($betrag < 5.0) {
            $zuzahlung = round($betrag, 2);
        }
        $zuzahlungZahlart = isset($selectedByRideFromDb[$idx]['zuzahlung_zahlart']) ? (string)$selectedByRideFromDb[$idx]['zuzahlung_zahlart'] : 'bar';
        $ohneZuzahlung = isset($selectedByRideFromDb[$idx]['zuzahlung']) ? ((float)$selectedByRideFromDb[$idx]['zuzahlung'] <= 0.0001) : false;

        if (isset($postedRides[$idx]) && is_array($postedRides[$idx])) {
            $catRaw = strtolower(trim((string)($postedRides[$idx]['kategorie'] ?? '')));
            if (in_array($catRaw, $categoryKeys, true)) {
                $cat = $catRaw;
            }

            $mwstRaw = (int)($postedRides[$idx]['mwst'] ?? 7);
            if (in_array($mwstRaw, [0, 7, 19], true)) {
                $mwst = $mwstRaw;
            }

            $karteRaw = str_replace(',', '.', (string)($postedRides[$idx]['karte_betrag'] ?? ''));
            if ($karteRaw !== '' && is_numeric($karteRaw)) {
                $karteBetrag = max(0.0, min($betrag, round((float)$karteRaw, 2)));
            }

            $zzRaw = strtolower(trim((string)($postedRides[$idx]['zuzahlung_zahlart'] ?? 'bar')));
            if (in_array($zzRaw, ['bar', 'karte'], true)) {
                $zuzahlungZahlart = $zzRaw;
            }
            $ohneZuzahlung = isset($postedRides[$idx]['ohne_zuzahlung']) && (string)$postedRides[$idx]['ohne_zuzahlung'] === '1';
        }

        if ($cat === 'krankenfahrt') {
            if ($ohneZuzahlung) { $zuzahlung = 0.0; }
            $krankenAnteil = max(0.0, round($betrag - $zuzahlung, 2));
            $totals['krankenfahrt'] += $krankenAnteil;
            $totals[$zuzahlungZahlart] += $zuzahlung;
        } elseif ($cat === 'karte') {
            $totals['karte'] += $karteBetrag;
            $totals['bar'] += max(0.0, round($betrag - $karteBetrag, 2));
        } else {
            $totals[$cat] += $betrag;
        }
        $totals['summe'] += $betrag;

        $selectedByRide[$idx] = [
            'kategorie' => $cat,
            'mwst' => $mwst,
            'betrag' => $betrag,
            'karte_betrag' => $karteBetrag,
            'zuzahlung' => $zuzahlung,
            'zuzahlung_zahlart' => $zuzahlungZahlart,
            'ohne_zuzahlung' => $ohneZuzahlung,
        ];
    }

    $maxBetrag = 10000;
    $sanitizeExpense = static function (string $raw, string $label) use (&$fieldErrors, $maxBetrag): float {
        if ($raw === '') return 0.0;
        $normalized = str_replace([' ', ','], ['', '.'], $raw);
        $betrag = filter_var($normalized, FILTER_VALIDATE_FLOAT);
        if ($betrag === false || $betrag < 0 || $betrag > $maxBetrag) {
            $fieldErrors[$label] = 'Bitte gültigen Betrag zwischen 0,00 und ' . number_format($maxBetrag, 2, ',', '.') . ' € eingeben.';
            return 0.0;
        }
        return round((float)$betrag, 2);
    };

    $tankenWaschen = $sanitizeExpense($expenses['tanken_waschen'], 'tanken_waschen');
    $sonstigeAusgaben = $sanitizeExpense($expenses['sonstige_ausgaben'], 'sonstige_ausgaben');

    // Datum-Validierung wie bisher
    if ($datum === '') {
        $fieldErrors['datum'] = 'Bitte ein Datum auswählen.';
    } else {
        $appTimezone = new DateTimeZone('Europe/Berlin');
        $datumObj = DateTimeImmutable::createFromFormat('!Y-m-d', $datum, $appTimezone);
        if (!$datumObj || $datumObj->format('Y-m-d') !== $datum) {
            $fieldErrors['datum'] = 'Bitte ein gültiges Datum im Format JJJJ-MM-TT wählen.';
        } else {
            $heute = (new DateTimeImmutable('now', $appTimezone))->setTime(0, 0);
            if ($datumObj > $heute) {
                $fieldErrors['datum'] = 'Das Datum darf nicht in der Zukunft liegen.';
            }
        }
    }

    if ($totals['summe'] <= 0) {
        $errorMessages[] = 'Es wurden keine Fahrten mit Betrag gefunden bzw. ausgewählt.';
    }

    if (empty($fieldErrors['datum'])) {
        $duplikatStmt = $pdo->prepare('SELECT COUNT(*) FROM Umsatz WHERE FahrerID = ? AND Datum = ?');
        $duplikatStmt->execute([$fahrer_id, $datum]);
        if ((int)$duplikatStmt->fetchColumn() > 0) {
            $fieldErrors['datum'] = 'Für dieses Datum wurde bereits ein Umsatz erfasst.';
        }
    }

    if (empty($fieldErrors) && empty($errorMessages)) {
        $notizParts = [];
        if ($expenses['notiz'] !== '') {
            $notizParts[] = $expenses['notiz'];
        }

        $mwstAgg = [0 => 0.0, 7 => 0.0, 19 => 0.0];
        foreach ($selectedByRide as $sel) {
            $mwstAgg[(int)$sel['mwst']] += (float)$sel['betrag'];
        }
        $notizParts[] = 'MVP/neu: Fahrten=' . count($selectedByRide) . ', MwSt[0]=' . number_format($mwstAgg[0], 2, '.', '') . ', MwSt[7]=' . number_format($mwstAgg[7], 2, '.', '') . ', MwSt[19]=' . number_format($mwstAgg[19], 2, '.', '');
        $notiz = implode("\n", $notizParts);

        try {
            $fahrerStmt = $pdo->prepare('SELECT Vorname, Nachname FROM Fahrer WHERE FahrerID = ?');
            $fahrerStmt->execute([$fahrer_id]);
            $fahrer = $fahrerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$fahrer) {
                throw new RuntimeException('Fahrer nicht gefunden.');
            }

            $sumTaxameter = 0.0;
            $sumOhneTaxameter = 0.0;
            foreach ($fahrten as $idx => $fahrt) {
                $sel = $selectedByRide[$idx] ?? null;
                $betragRide = isset($sel['betrag']) && is_numeric($sel['betrag'])
                    ? (float)$sel['betrag']
                    : (isset($fahrt['betrag']) && is_numeric($fahrt['betrag']) ? (float)$fahrt['betrag'] : 0.0);

                $isManualRide = !empty($fahrt['raw']['manual']) || !empty($fahrt['fms_match']['manual']);
                if ($isManualRide) {
                    $sumOhneTaxameter += $betragRide;
                } else {
                    $sumTaxameter += $betragRide;
                }
            }
            $sumTaxameter = round($sumTaxameter, 2);
            $sumOhneTaxameter = round($sumOhneTaxameter, 2);

            $pdo->beginTransaction();

            // NEU-Schema (Dual-Write): Kopf + Fahrten-Details
            $insertNeuHead = $pdo->prepare("
                INSERT INTO driver_abrechnung_neu (
                    fahrer_id,
                    datum,
                    taxameter_umsatz,
                    ohne_taxameter,
                    kartenzahlung,
                    rechnungsfahrten,
                    krankenfahrten,
                    gutscheine,
                    alita,
                    tanken_waschen,
                    sonstige_ausgaben,
                    fahrten_summe,
                    notiz
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    taxameter_umsatz = VALUES(taxameter_umsatz),
                    ohne_taxameter = VALUES(ohne_taxameter),
                    kartenzahlung = VALUES(kartenzahlung),
                    rechnungsfahrten = VALUES(rechnungsfahrten),
                    krankenfahrten = VALUES(krankenfahrten),
                    gutscheine = VALUES(gutscheine),
                    alita = VALUES(alita),
                    tanken_waschen = VALUES(tanken_waschen),
                    sonstige_ausgaben = VALUES(sonstige_ausgaben),
                    fahrten_summe = VALUES(fahrten_summe),
                    notiz = VALUES(notiz)
            ");

            $insertNeuHead->execute([
                $fahrer_id,
                $datum,
                $sumTaxameter,
                $sumOhneTaxameter,
                $totals['karte'],
                $totals['rechnung'],
                $totals['krankenfahrt'],
                $totals['gutschein'],
                $totals['alita'],
                $tankenWaschen,
                $sonstigeAusgaben,
                $totals['summe'],
                $notiz !== '' ? $notiz : null,
            ]);

            $idStmt = $pdo->prepare('SELECT id FROM driver_abrechnung_neu WHERE fahrer_id = ? AND datum = ? LIMIT 1');
            $idStmt->execute([$fahrer_id, $datum]);
            $abrechnungNeuId = (int)$idStmt->fetchColumn();

            $pdo->prepare('DELETE FROM driver_abrechnung_neu_fahrten WHERE abrechnung_neu_id = ?')->execute([$abrechnungNeuId]);

            $insertNeuDetail = $pdo->prepare("\n                INSERT INTO driver_abrechnung_neu_fahrten (
                    abrechnung_neu_id,
                    fahrer_id,
                    datum,
                    ride_idx,
                    taxidaten_id,
                    taxidaten_e_link,
                    fms_auftrag_id,
                    zeitpunkt,
                    von_text,
                    nach_text,
                    brutto,
                    netto,
                    mwst_satz,
                    kategorie,
                    flags_json,
                    raw_snapshot_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($fahrten as $idx => $fahrt) {
                $sel = $selectedByRide[$idx] ?? ['kategorie' => 'bar', 'mwst' => 7, 'betrag' => 0.0, 'karte_betrag' => 0.0, 'zuzahlung' => 0.0, 'zuzahlung_zahlart' => 'bar'];
                $raw = is_array($fahrt['raw'] ?? null) ? $fahrt['raw'] : [];

                $brutto = isset($fahrt['betrag']) && is_numeric($fahrt['betrag']) ? round((float)$fahrt['betrag'], 2) : 0.0;
                $netto = null;
                foreach (['netto', 'netto_betrag', 'betrag_netto'] as $field) {
                    if (isset($raw[$field]) && is_numeric($raw[$field])) {
                        $netto = round((float)$raw[$field], 2);
                        break;
                    }
                }

                $zeitpunkt = null;
                if (($fahrt['zeit_obj'] ?? null) instanceof DateTimeInterface) {
                    $zeitpunkt = $fahrt['zeit_obj']->format('Y-m-d H:i:s');
                } else {
                    $tmpDt = driverParseDateTimeLoose((string)($fahrt['zeit'] ?? ''));
                    $zeitpunkt = $tmpDt ? $tmpDt->format('Y-m-d H:i:s') : null;
                }

                $flags = [
                    'has_fms_match' => isset($fahrt['fms_match']),
                    'fms_delta_sec' => $fahrt['fms_match']['delta_sec'] ?? null,
                    'source' => 'taxidaten+fms',
                    'karte_betrag' => (float)($sel['karte_betrag'] ?? 0.0),
                    'zuzahlung' => (float)($sel['zuzahlung'] ?? 0.0),
                    'zuzahlung_zahlart' => (string)($sel['zuzahlung_zahlart'] ?? 'bar'),
                ];

                $insertNeuDetail->execute([
                    $abrechnungNeuId,
                    $fahrer_id,
                    $datum,
                    (int)$idx,
                    isset($fahrt['id']) ? (string)$fahrt['id'] : null,
                    isset($fahrt['e_link']) ? (string)$fahrt['e_link'] : null,
                    isset($fahrt['fms_match']['auftrag_id']) ? (string)$fahrt['fms_match']['auftrag_id'] : null,
                    $zeitpunkt,
                    trim((string)($fahrt['von'] ?? '')) !== '' ? (string)$fahrt['von'] : null,
                    trim((string)($fahrt['nach'] ?? '')) !== '' ? (string)$fahrt['nach'] : null,
                    $brutto,
                    $netto,
                    (int)$sel['mwst'],
                    (string)$sel['kategorie'],
                    json_encode($flags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            // ALT-Schema (Kompatibilität)
            $insert = $pdo->prepare("\n                INSERT INTO Umsatz (
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

            $insert->execute([
                $fahrer_id,
                $datum,
                $sumTaxameter,
                $sumOhneTaxameter,
                $totals['karte'],
                $totals['rechnung'],
                $totals['krankenfahrt'],
                $totals['gutschein'],
                $totals['alita'],
                $tankenWaschen,
                $sonstigeAusgaben,
                $notiz !== '' ? $notiz : null,
            ]);

            $notificationStmt = $pdo->prepare("\n                INSERT INTO notifications (Vorname, Nachname, Umsatz, Datum, gesendet)
                VALUES (?, ?, ?, ?, ?)
            ");
            $notificationStmt->execute([
                $fahrer['Vorname'],
                $fahrer['Nachname'],
                $totals['summe'],
                $datum,
                0,
            ]);

            $pdo->commit();
            header('Location: dashboard.php');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessages[] = 'Beim Speichern ist ein Fehler aufgetreten: ' . $e->getMessage();
            error_log('abrechnung_neu save error: ' . $e->getMessage());
        }
    }
} else {
    foreach ($fahrten as $idx => $fahrt) {
        $betrag = isset($fahrt['betrag']) && is_numeric($fahrt['betrag']) ? round((float)$fahrt['betrag'], 2) : 0.0;
        $defaultKategorie = isset($selectedByRideFromDb[$idx]['kategorie']) ? (string)$selectedByRideFromDb[$idx]['kategorie'] : driverDetectDefaultKategorie($fahrt);
        if (!in_array($defaultKategorie, $categoryKeys, true)) {
            $defaultKategorie = 'bar';
        }
        $defaultMwst = isset($selectedByRideFromDb[$idx]['mwst']) ? (int)$selectedByRideFromDb[$idx]['mwst'] : 7;
        if (!in_array($defaultMwst, [0, 7, 19], true)) {
            $defaultMwst = 7;
        }

        $karteBetrag = isset($selectedByRideFromDb[$idx]['karte_betrag']) ? (float)$selectedByRideFromDb[$idx]['karte_betrag'] : $betrag;
        $zuzahlung = round(min(10.0, max(5.0, $betrag * 0.10)), 2);
        if ($betrag < 5.0) {
            $zuzahlung = round($betrag, 2);
        }
        $zuzahlungZahlart = isset($selectedByRideFromDb[$idx]['zuzahlung_zahlart']) ? (string)$selectedByRideFromDb[$idx]['zuzahlung_zahlart'] : 'bar';

        if ($defaultKategorie === 'krankenfahrt') {
            $totals['krankenfahrt'] += max(0.0, round($betrag - $zuzahlung, 2));
            $totals[$zuzahlungZahlart] += $zuzahlung;
        } elseif ($defaultKategorie === 'karte') {
            $totals['karte'] += $karteBetrag;
            $totals['bar'] += max(0.0, round($betrag - $karteBetrag, 2));
        } else {
            $totals[$defaultKategorie] += $betrag;
        }
        $totals['summe'] += $betrag;

        $selectedByRide[$idx] = [
            'kategorie' => $defaultKategorie,
            'mwst' => $defaultMwst,
            'betrag' => $betrag,
            'karte_betrag' => $karteBetrag,
            'zuzahlung' => $zuzahlung,
            'zuzahlung_zahlart' => $zuzahlungZahlart,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Abrechnung (neu) | DRIVE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/driver-dashboard.css">
    <link rel="stylesheet" href="css/umsatz.css">
    <link rel="stylesheet" href="css/form-feedback.css">
    <style>
        .ride-list { display:flex; flex-direction:column; gap:10px; margin-bottom:14px; }
        .ride-item { background:#fff; border:1px solid #dce3ef; border-radius:10px; padding:10px; }
        .ride-time { font-weight:700; margin-bottom:6px; }
        .ride-route { display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:8px; }
        .ride-block { font-size:.95rem; line-height:1.25; }
        .ride-label { color:#667085; font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
        .ride-controls { display:grid; grid-template-columns: 80px 110px 1fr; gap:8px; align-items:start; }
        .ride-controls input,.ride-controls select { width:100%; }
        .betrag { text-align:right; white-space:nowrap; }
        .sum-grid { display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:10px; }
        .sum-card { background:#eef4ff; border:1px solid #c8daf8; border-radius:10px; padding:10px; }
        .sum-card strong { float:right; }
        .muted { color:#5f6978; font-size:.9rem; }
    </style>
</head>
<body>
<?php include 'bottom_nav.php'; ?>
<main>
    <h1>Abrechnung (neu)</h1>

    <?php if (!empty($errorMessages)): ?>
        <div class="form-feedback form-feedback--error"><ul><?php foreach ($errorMessages as $msg): ?><li><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if (!empty($apiWarnings)): ?>
        <div class="form-feedback form-feedback--warning"><ul><?php foreach ($apiWarnings as $msg): ?><li><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="abrechnung_neu.php" id="abrechnungNeuForm">
        <?php if ($recherche_flag === 1): ?>
            <p class="recherche-hinweis"><a href="recherche.php" class="recherche-link">Auftragsrecherche</a></p>
        <?php endif; ?>

        <label for="datum">Datum (offene Schichten):</label>
        <?php if (empty($offene_daten)): ?>
            <input type="date" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>
            <p class="hinweis">⚠️ Keine offene Schicht gefunden – bitte Datum manuell prüfen.</p>
        <?php else: ?>
            <select name="datum" id="datum" required onchange="this.form.submit()">
                <?php foreach ($verfuegbare_daten as $auswahlDatum): ?>
                    <?php $anzeige = driverFormatGermanDateOrOriginal($auswahlDatum); if (!in_array($auswahlDatum, $offene_daten, true)) { $anzeige .= ' (manuelle Auswahl)'; } ?>
                    <option value="<?= htmlspecialchars($auswahlDatum) ?>" <?= $datum === $auswahlDatum ? 'selected' : '' ?>><?= htmlspecialchars($anzeige) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if (!empty($fieldErrors['datum'])): ?><p class="field-error"><?= htmlspecialchars($fieldErrors['datum']) ?></p><?php endif; ?>


        <div class="ride-list">
            <?php if (empty($fahrten)): ?>
                <div class="ride-item muted">Keine Fahrten gefunden. Seite bleibt bedienbar, Ausgaben/Notiz können dennoch erfasst werden.</div>
            <?php else: ?>
                <?php foreach ($fahrten as $idx => $fahrt): ?>
                    <?php
                        $sel = $selectedByRide[$idx] ?? ['kategorie' => 'bar', 'mwst' => 7, 'betrag' => 0.0, 'karte_betrag' => 0.0, 'zuzahlung' => 0.0, 'zuzahlung_zahlart' => 'bar'];
                        $zeitText = '';
                        if ($fahrt['zeit_obj'] instanceof DateTimeInterface) {
                            $zeitText = $fahrt['zeit_obj']->format('H:i');
                        } elseif (!empty($fahrt['zeit'])) {
                            $dtTmp = driverParseDateTimeLoose((string)$fahrt['zeit']);
                            $zeitText = $dtTmp ? $dtTmp->format('H:i') : (string)$fahrt['zeit'];
                        } elseif (!empty($fahrt['fms_match']['zeit'])) {
                            $dtTmp = driverParseDateTimeLoose((string)$fahrt['fms_match']['zeit']);
                            $zeitText = $dtTmp ? $dtTmp->format('H:i') : '';
                        }
                        if ($zeitText === '') {
                            $zeitText = '--:--';
                        }
                    ?>
                    <article class="ride-item">
                        <div class="ride-time">
                            <?= htmlspecialchars($zeitText) ?>
                            <?php if (!empty($fahrt['fms_match']['pauschalfahrt'])): ?>
                                <span class="muted" style="margin-left:6px; font-weight:600;">(Pauschalfahrt/FMS)</span>
                            <?php endif; ?>
                        </div>
                        <div class="ride-route">
                            <div class="ride-block">
                                <div class="ride-label">Von</div>
                                <div><?= nl2br(htmlspecialchars((string)($fahrt['von'] ?? ''))) ?></div>
                            </div>
                            <div class="ride-block">
                                <div class="ride-label">Nach</div>
                                <div><?= nl2br(htmlspecialchars((string)($fahrt['nach'] ?? ''))) ?></div>
                            </div>
                        </div>
                        <div class="ride-controls">
                            <select name="fahrten[<?= (int)$idx ?>][mwst]" class="mwst-select">
                                <?php foreach ([0,7,19] as $mw): ?><option value="<?= $mw ?>" <?= (int)$sel['mwst'] === $mw ? 'selected' : '' ?>><?= $mw ?>%</option><?php endforeach; ?>
                            </select>
                            <select name="fahrten[<?= (int)$idx ?>][kategorie]" class="cat-select">
                                <?php $labels = ['bar'=>'Bar','karte'=>'Karte','rechnung'=>'Rechnung','krankenfahrt'=>'Krankenfahrt','gutschein'=>'Gutschein','alita'=>'Alita']; foreach ($labels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $sel['kategorie'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" class="ride-amount" name="fahrten[<?= (int)$idx ?>][betrag]" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float)($sel['betrag'] ?? (float)($fahrt['betrag'] ?? 0)),2,'.','')) ?>">
                        </div>
                        <div class="ride-extra ride-extra-karte" style="margin-top:6px; display:<?= $sel['kategorie'] === 'karte' ? 'block' : 'none' ?>;">
                            <label class="muted" style="display:block;">davon Karte (€)</label>
                            <input type="number" class="karte-betrag-input" name="fahrten[<?= (int)$idx ?>][karte_betrag]" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float)($sel['karte_betrag'] ?? $sel['betrag'] ?? 0),2,'.','')) ?>">
                        </div>
                        <div class="ride-extra ride-extra-krank" style="margin-top:6px; display:<?= $sel['kategorie'] === 'krankenfahrt' ? 'block' : 'none' ?>;">
                            <?php $zz = (float)($sel['zuzahlung'] ?? 0.0); ?>
                            <div class="muted">Zuzahlung: <strong class="zuzahlung-display"><?= number_format($zz,2,',','.') ?> €</strong></div>
                            <label style="display:block;margin:6px 0;"><input type="checkbox" name="fahrten[<?= (int)$idx ?>][ohne_zuzahlung]" value="1" class="ohne-zuzahlung-checkbox" <?= !empty($sel['ohne_zuzahlung']) ? 'checked' : '' ?>> ohne Zuzahlung</label>
                            <label class="muted" style="display:block;">Zuzahlung bezahlt per</label>
                            <select name="fahrten[<?= (int)$idx ?>][zuzahlung_zahlart]" class="zuzahlung-zahlart-select">
                                <option value="bar" <?= (($sel['zuzahlung_zahlart'] ?? 'bar') === 'bar') ? 'selected' : '' ?>>Bar</option>
                                <option value="karte" <?= (($sel['zuzahlung_zahlart'] ?? 'bar') === 'karte') ? 'selected' : '' ?>>Karte</option>
                            </select>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="margin:10px 0 14px;">
            <button type="button" id="addManualRideBtn">+ Manuelle Fahrt hinzufügen</button>
        </div>

        <fieldset class="blau">
            <legend>Auto-Summen (bisher blauer Bereich + Bar)</legend>
            <div class="sum-grid">
                <div class="sum-card">Bar <strong id="sum-bar">0,00 €</strong></div>
                <div class="sum-card">Kartenzahlung <strong id="sum-karte">0,00 €</strong></div>
                <div class="sum-card">Rechnungsfahrten <strong id="sum-rechnung">0,00 €</strong></div>
                <div class="sum-card">Krankenfahrten <strong id="sum-krankenfahrt">0,00 €</strong></div>
                <div class="sum-card">Gutscheine <strong id="sum-gutschein">0,00 €</strong></div>
                <div class="sum-card">Alita <strong id="sum-alita">0,00 €</strong></div>
                <div class="sum-card" style="grid-column:1 / -1; background:#deebff;">Gesamtsumme Fahrten <strong id="sum-gesamt">0,00 €</strong></div>
            </div>
        </fieldset>

        <fieldset class="rot">
            <legend>Ausgaben</legend>
            <label for="tanken_waschen">Tanken/Waschen (€):</label>
            <input type="number" id="tanken_waschen" name="tanken_waschen" step="0.01" min="0" value="<?= htmlspecialchars($expenses['tanken_waschen']) ?>">
            <?php if (!empty($fieldErrors['tanken_waschen'])): ?><p class="field-error"><?= htmlspecialchars($fieldErrors['tanken_waschen']) ?></p><?php endif; ?>

            <label for="sonstige_ausgaben">Sonstige Ausgaben (€):</label>
            <input type="number" id="sonstige_ausgaben" name="sonstige_ausgaben" step="0.01" min="0" value="<?= htmlspecialchars($expenses['sonstige_ausgaben']) ?>">
            <?php if (!empty($fieldErrors['sonstige_ausgaben'])): ?><p class="field-error"><?= htmlspecialchars($fieldErrors['sonstige_ausgaben']) ?></p><?php endif; ?>
        </fieldset>

        <label for="notiz">Notiz (optional):</label>
        <textarea id="notiz" name="notiz" rows="4" cols="50"><?= htmlspecialchars($expenses['notiz']) ?></textarea>

        <button type="submit">Umsatz speichern</button>
    </form>
</main>
<?php include 'nav-script.php'; ?>
<script>
(function(){
    function parseAmount(el){
        var n = parseFloat((el && el.value) ? el.value : '0');
        return isNaN(n) ? 0 : n;
    }
    function fmt(v){
        return v.toLocaleString('de-DE',{minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
    }

    var form = document.getElementById('abrechnungNeuForm');
    if(!form) return;

    var rideList = form.querySelector('.ride-list');
    var addManualBtn = document.getElementById('addManualRideBtn');

    function createManualRideRow(){
        if(!rideList) return;
        var key = 'manual_' + Date.now() + '_' + Math.floor(Math.random()*1000);
        var html = ''+
        '<article class="ride-item">'+
        '  <div class="ride-time">manuell</div>'+
        '  <div class="ride-route">'+
        '    <div class="ride-block"><div class="ride-label">Von</div><input type="text" name="fahrten['+key+'][von]" placeholder="Abholort"></div>'+
        '    <div class="ride-block"><div class="ride-label">Nach</div><input type="text" name="fahrten['+key+'][nach]" placeholder="Ziel"></div>'+
        '  </div>'+
        '  <div class="ride-controls">'+
        '    <select name="fahrten['+key+'][mwst]" class="mwst-select"><option value="0">0%</option><option value="7" selected>7%</option><option value="19">19%</option></select>'+
        '    <select name="fahrten['+key+'][kategorie]" class="cat-select"><option value="bar" selected>Bar</option><option value="karte">Karte</option><option value="rechnung">Rechnung</option><option value="krankenfahrt">Krankenfahrt</option><option value="gutschein">Gutschein</option><option value="alita">Alita</option></select>'+
        '    <input type="number" class="ride-amount" name="fahrten['+key+'][betrag]" step="0.01" min="0" value="0.00">'+
        '  </div>'+
        '  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">'+
        '    <div><label class="muted">Zeit (HH:MM)</label><input type="text" name="fahrten['+key+'][zeit]" placeholder="z.B. 08:15"></div>'+
        '    <div><input type="hidden" name="fahrten['+key+'][manual]" value="1"></div>'+
        '  </div>'+
        '  <div class="ride-extra ride-extra-karte" style="margin-top:6px; display:none;">'+
        '    <label class="muted" style="display:block;">davon Karte (€)</label>'+
        '    <input type="number" class="karte-betrag-input" name="fahrten['+key+'][karte_betrag]" step="0.01" min="0" value="0.00">'+
        '  </div>'+
        '  <div class="ride-extra ride-extra-krank" style="margin-top:6px; display:none;">'+
        '    <div class="muted">Zuzahlung: <strong class="zuzahlung-display">0,00 €</strong></div>'+
        '    <label class="muted" style="display:block;">Zuzahlung bezahlt per</label>'+
        '    <select name="fahrten['+key+'][zuzahlung_zahlart]" class="zuzahlung-zahlart-select"><option value="bar" selected>Bar</option><option value="karte">Karte</option></select>'+
        '  </div>'+
        '</article>';
        rideList.insertAdjacentHTML('beforeend', html);
    }

    if(addManualBtn){
        addManualBtn.addEventListener('click', function(){
            createManualRideRow();
            recalc();
        });
    }

    function calcZuzahlung(amount){
        var z = amount * 0.10;
        if (z < 5) z = 5;
        if (z > 10) z = 10;
        if (z > amount) z = amount;
        return Math.round(z * 100) / 100;
    }

    function recalc(){
        var sums = {bar:0,karte:0,rechnung:0,krankenfahrt:0,gutschein:0,alita:0,gesamt:0};
        var rows = form.querySelectorAll('.ride-item');
        rows.forEach(function(row){
            var amountInput = row.querySelector('.ride-amount');
            var catSelect = row.querySelector('.cat-select');
            if(!amountInput || !catSelect) return;
            var amount = parseAmount(amountInput);
            var cat = catSelect.value || 'bar';
            if(!(cat in sums)) cat = 'bar';

            var extraKarte = row.querySelector('.ride-extra-karte');
            var extraKrank = row.querySelector('.ride-extra-krank');
            if (extraKarte) extraKarte.style.display = (cat === 'karte') ? 'block' : 'none';
            if (extraKrank) extraKrank.style.display = (cat === 'krankenfahrt') ? 'block' : 'none';

            if (cat === 'karte') {
                var kb = row.querySelector('.karte-betrag-input');
                var k = kb ? parseAmount(kb) : amount;
                if (k < 0) k = 0;
                if (k > amount) k = amount;
                if (kb) kb.value = k.toFixed(2);
                sums.karte += k;
                sums.bar += Math.max(0, amount - k);
            } else if (cat === 'krankenfahrt') {
                var noCopay = row.querySelector('.ohne-zuzahlung-checkbox');
                var withoutCopay = !!(noCopay && noCopay.checked);
                var zz = withoutCopay ? 0 : calcZuzahlung(amount);
                var zzDisplay = row.querySelector('.zuzahlung-display');
                if (zzDisplay) zzDisplay.textContent = fmt(zz);
                var zzPay = row.querySelector('.zuzahlung-zahlart-select');
                var pay = zzPay ? zzPay.value : 'bar';
                if (zzPay) zzPay.disabled = withoutCopay;
                sums.krankenfahrt += Math.max(0, amount - zz);
                if (zz > 0) { if (pay === 'karte') sums.karte += zz; else sums.bar += zz; }
            } else {
                sums[cat] += amount;
            }

            sums.gesamt += amount;
        });

        Object.keys(sums).forEach(function(k){
            var target = document.getElementById('sum-' + k);
            if(target) target.textContent = fmt(sums[k]);
        });
    }

    form.addEventListener('input', function(e){
        if (e.target && (e.target.classList.contains('karte-betrag-input') || e.target.classList.contains('ride-amount'))) {
            recalc();
        }
    });

    form.addEventListener('change', function(e){
        if (e.target && (e.target.classList.contains('cat-select') || e.target.classList.contains('zuzahlung-zahlart-select') || e.target.classList.contains('ohne-zuzahlung-checkbox'))) {
            recalc();
        }
    });

    recalc();
})();
</script>
</body>
</html>
