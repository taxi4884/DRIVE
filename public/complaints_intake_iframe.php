<?php
require_once '../includes/db.php';
require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header_remove('X-Frame-Options');

$canUse = true;

$ok = '';
$err = '';
$warn = '';

$pdo->exec("ALTER TABLE complaint_entrepreneurs ADD COLUMN IF NOT EXISTS external_number VARCHAR(30) NULL AFTER company_name");
$pdo->exec("ALTER TABLE complaints MODIFY COLUMN status ENUM('neu','pruefen','in_pruefung','geschlossen') NOT NULL DEFAULT 'neu'");
$pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS intake_employee_code VARCHAR(40) NULL AFTER created_by");

function v(array $row, array $keys): ?string {
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            return trim((string)$row[$k]);
        }
    }
    return null;
}

function gatewayCall(array $payload): array {
    $ch = curl_init('https://4884gateway.de/fms');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $decoded = null;
    if (is_string($raw) && $raw !== '') {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    }

    return [
        'ok' => ($curlErr === '' && $code >= 200 && $code < 300),
        'http_code' => $code,
        'curl_error' => $curlErr,
        'raw' => is_string($raw) ? $raw : '',
        'decoded' => $decoded,
    ];
}

function findByNumberRecursive($node, string $number, array $keys): ?array {
    $norm = ltrim($number, '0');
    $norm = $norm === '' ? '0' : $norm;

    if (is_array($node)) {
        $map = [];
        foreach ($node as $k => $val) {
            $map[strtolower((string)$k)] = $val;
        }
        foreach ($keys as $k) {
            $lk = strtolower($k);
            if (!isset($map[$lk])) continue;
            $candidate = ltrim((string)$map[$lk], '0');
            $candidate = $candidate === '' ? '0' : $candidate;
            if ($candidate === $norm) {
                return $node;
            }
        }
        foreach ($node as $child) {
            $found = findByNumberRecursive($child, $number, $keys);
            if ($found) return $found;
        }
    }
    return null;
}

function loadDriverFromGateway(string $driverNo): ?array {
    $attempts = [
        ['GETFAHRER' => ['FAHRER_NR' => (int)$driverNo]],
        ['GETFAHRER' => ['nummer' => (int)$driverNo]],
        ['getfahrer' => ['fahrernummer' => $driverNo]],
        ['GETFAHRERLISTE' => new stdClass()],
    ];

    foreach ($attempts as $payload) {
        $result = gatewayCall($payload);
        $response = $result['decoded'];
        if (!is_array($response)) continue;

        $candidate = findByNumberRecursive($response, $driverNo, ['fahrerNr','fahrernummer','fahrer_nr','nummer','fahrernr','displaynummer']);
        if (!$candidate) {
            continue;
        }

        return [
            'display_number' => (string)(v($candidate, ['displaynummer','display_number']) ?? $driverNo),
            'driver_number' => v($candidate, ['fahrerNr','fahrernummer','fahrer_nr','nummer','fahrernr']) ?? $driverNo,
            'first_name' => v($candidate, ['vorname','first_name']),
            'last_name' => v($candidate, ['nachname','last_name']),
            'street' => v($candidate, ['strasseName','strasse','street']),
            'house_no' => v($candidate, ['hausnummerEcke','hausnummer','house_no']),
            'postal_code' => v($candidate, ['plz','postal_code']),
            'city' => v($candidate, ['ortName','ort','city']),
            'email' => v($candidate, ['email','e_mail']),
            'mobile' => v($candidate, ['mobiltelefonnummer','handynummer','mobilnummer','mobile']),
            'entrepreneur_number' => v($candidate, ['unternehmerNr','unternehmernummer','unternehmer_nr','unternehmer']),
            'raw_payload' => json_encode($response, JSON_UNESCAPED_UNICODE),
        ];
    }
    return null;
}

function loadEntrepreneurFromGateway(string $number): ?array {
    $attempts = [
        ['GETUNTERNEHMER' => ['UNTERNEHMER_NR' => (int)$number]],
        ['GETUNTERNEHMER' => ['nummer' => (int)$number]],
        ['getunternehmer' => ['unternehmernummer' => $number]],
        ['GETUNTERNEHMERLISTE' => new stdClass()],
    ];

    foreach ($attempts as $payload) {
        $result = gatewayCall($payload);
        $response = $result['decoded'];
        if (!is_array($response)) continue;

        $candidate = findByNumberRecursive($response, $number, ['unternehmerNr','unternehmernummer','unternehmer_nr','nummer']);
        if (!$candidate) continue;

        return [
            'external_number' => (string)(v($candidate, ['unternehmerNr','unternehmernummer','unternehmer_nr','nummer']) ?? $number),
            'company_name' => (string)(v($candidate, ['firma','firmenname','company_name','name']) ?? ('Unternehmer #' . $number)),
            'first_name' => v($candidate, ['vorname','first_name']),
            'last_name' => v($candidate, ['nachname','last_name']),
            'street' => v($candidate, ['strasseName','strasse','street']),
            'house_no' => v($candidate, ['hausnummerEcke','hausnummer','house_no']),
            'postal_code' => v($candidate, ['plz','postal_code']),
            'city' => v($candidate, ['ortName','ort','city']),
            'email' => v($candidate, ['email','e_mail']),
            'phone' => v($candidate, ['telefon','phone']),
            'mobile' => v($candidate, ['mobiltelefonnummer','mobilnummer','mobile']),
        ];
    }
    return null;
}

function templateByType(string $type, string $orderNo, string $freeText): string {
    $base = [
        'not_driven' => "Der Auftrag {$orderNo} wurde von Ihnen angenommen, jedoch nicht ausgeführt. Nach Annahme eines Auftrags besteht die Verpflichtung, diesen ordnungsgemäß durchzuführen oder – sofern dies nicht möglich ist – unverzüglich die Zentrale zu informieren, damit eine alternative Disposition erfolgen kann. Eine entsprechende Information ist nicht erfolgt. Dieses Vorgehen stellt einen Verstoß gegen die vereinbarten Abläufe dar.",
        'wrong_passenger' => "Der Auftrag {$orderNo} wurde von Ihnen angenommen und anschließend ohne ordnungsgemäße Durchführung storniert bzw. abgeschlossen. Der Auftrag wurde von Ihnen angenommen und anschließend ohne ordnungsgemäße Durchführung storniert bzw. abgeschlossen. Dieses Vorgehen stellt einen klaren Verstoß gegen die vereinbarten Abläufe dar.",
        'cancelled' => "Der Auftrag {$orderNo} wurde von Ihnen angenommen und ohne Durchführung storniert bzw. abgeschlossen. Ein solches Vorgehen ist nur nach vorheriger Abstimmung mit der Zentrale zulässig, um eine Weitervermittlung sicherzustellen. Eine entsprechende Abstimmung ist nicht erfolgt. Dieses Verhalten stellt einen Verstoß gegen die vereinbarten Abläufe dar.",
        'other' => trim($freeText),
    ];
    return trim((string)($base[$type] ?? $base['other']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $driverNoRaw = trim((string)($_POST['driver_number'] ?? ''));
        if ($driverNoRaw === '') {
            $parts = [
                trim((string)($_POST['driver_number_1'] ?? '')),
                trim((string)($_POST['driver_number_2'] ?? '')),
                trim((string)($_POST['driver_number_3'] ?? '')),
                trim((string)($_POST['driver_number_4'] ?? '')),
            ];
            $driverNoRaw = implode('', $parts);
        }
        $driverNo = preg_replace('/\D+/', '', $driverNoRaw ?? '');
        $orderNo = trim((string)($_POST['order_number'] ?? ''));
        $incidentType = trim((string)($_POST['incident_type'] ?? ''));
        $otherText = trim((string)($_POST['other_text'] ?? ''));
        $employeeCode = trim((string)($_POST['employee_code'] ?? ''));

        if ($driverNo === '' || $orderNo === '' || $incidentType === '' || $employeeCode === '') {
            throw new RuntimeException('Bitte Fahrernummer, Auftragsnummer, Vorfalltyp und Mitarbeiter-Kennung ausfüllen.');
        }
        if (strlen($driverNo) !== 4) {
            throw new RuntimeException('Bitte die <strong>Fahrernummer</strong> (4-stellig) und nicht die Fahrzeugnummer angeben.');
        }
        if (!preg_match('/^\d{8}$/', $orderNo)) {
            throw new RuntimeException('Bitte die Auftragsnummer 8-stellig eingeben.');
        }
        if ($incidentType === 'other' && $otherText === '') {
            throw new RuntimeException('Bitte bei "Anderes" den Freitext erfassen.');
        }

        $driverStmt = $pdo->prepare('SELECT * FROM complaint_drivers WHERE driver_number = ? OR display_number = ? LIMIT 1');
        $driverStmt->execute([$driverNo, $driverNo]);
        $driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC);

        $driverData = loadDriverFromGateway($driverNo);
        if ($driverData && !$driverRow) {
            $ins = $pdo->prepare('INSERT INTO complaint_drivers (display_number, driver_number, first_name, last_name, street, house_no, postal_code, city, email, mobile, raw_payload) VALUES (:display,:dno,:first,:last,:street,:house,:plz,:city,:email,:mobile,:raw)');
            $ins->execute([
                ':display' => $driverData['display_number'],
                ':dno' => $driverData['driver_number'],
                ':first' => $driverData['first_name'],
                ':last' => $driverData['last_name'],
                ':street' => $driverData['street'],
                ':house' => $driverData['house_no'],
                ':plz' => $driverData['postal_code'],
                ':city' => $driverData['city'],
                ':email' => $driverData['email'],
                ':mobile' => $driverData['mobile'],
                ':raw' => $driverData['raw_payload'],
            ]);
            $driverId = (int)$pdo->lastInsertId();
            $driverRow = ['id' => $driverId, 'display_number' => $driverData['display_number']];
        } elseif ($driverRow && $driverData) {
            $upd = $pdo->prepare('UPDATE complaint_drivers SET display_number=:display, first_name=:first, last_name=:last, street=:street, house_no=:house, postal_code=:plz, city=:city, email=:email, mobile=:mobile, raw_payload=:raw WHERE id=:id');
            $upd->execute([
                ':display' => $driverData['display_number'],
                ':first' => $driverData['first_name'],
                ':last' => $driverData['last_name'],
                ':street' => $driverData['street'],
                ':house' => $driverData['house_no'],
                ':plz' => $driverData['postal_code'],
                ':city' => $driverData['city'],
                ':email' => $driverData['email'],
                ':mobile' => $driverData['mobile'],
                ':raw' => $driverData['raw_payload'],
                ':id' => (int)$driverRow['id'],
            ]);
        }

        $driverMissingInFms = false;
        if (!$driverRow && !$driverData) {
            $driverMissingInFms = true;
            $warn = 'Fahrernummer konnte in FMS nicht gefunden werden. Beschwerde wird trotzdem aufgenommen und zur Backoffice-Prüfung markiert.';
        }

        $entrepreneurId = null;
        if (!empty($driverData['entrepreneur_number'])) {
            $eno = (string)$driverData['entrepreneur_number'];
            $es = $pdo->prepare('SELECT id FROM complaint_entrepreneurs WHERE external_number = ? LIMIT 1');
            $es->execute([$eno]);
            $entrepreneurId = (int)$es->fetchColumn();

            if ($entrepreneurId <= 0) {
                $eData = loadEntrepreneurFromGateway($eno);
                if ($eData) {
                    $ei = $pdo->prepare('INSERT INTO complaint_entrepreneurs (external_number, company_name, first_name, last_name, street, house_no, postal_code, city, email, phone, mobile, active) VALUES (:nr,:company,:first,:last,:street,:house,:plz,:city,:email,:phone,:mobile,1)');
                    $ei->execute([
                        ':nr' => $eData['external_number'],
                        ':company' => $eData['company_name'],
                        ':first' => $eData['first_name'],
                        ':last' => $eData['last_name'],
                        ':street' => $eData['street'],
                        ':house' => $eData['house_no'],
                        ':plz' => $eData['postal_code'],
                        ':city' => $eData['city'],
                        ':email' => $eData['email'],
                        ':phone' => $eData['phone'],
                        ':mobile' => $eData['mobile'],
                    ]);
                    $entrepreneurId = (int)$pdo->lastInsertId();
                }
            }
        }

        $subject = templateByType($incidentType, $orderNo, $otherText);
        if ($subject === '') {
            throw new RuntimeException('Der Sachverhalt ist leer.');
        }

        $displayNo = (string)($driverData['display_number'] ?? $driverRow['display_number'] ?? $driverNo);
        if ($displayNo === '') {
            $displayNo = $driverNo;
        }

        if ($driverMissingInFms) {
            $subject = "[FMS-PRÜFUNG ERFORDERLICH] Fahrernummer {$driverNo} konnte nicht im System gefunden werden.\n" . $subject;
        }

        $driverIdForComplaint = isset($driverRow['id']) ? (int)$driverRow['id'] : null;

        $insComplaint = $pdo->prepare('INSERT INTO complaints (order_number, display_number, driver_id, entrepreneur_id, subject, action_type, status, created_by, intake_employee_code) VALUES (:order_number,:display_number,:driver_id,:entrepreneur_id,:subject,:action_type,:status,:created_by,:employee_code)');
        $insComplaint->execute([
            ':order_number' => $orderNo,
            ':display_number' => $displayNo,
            ':driver_id' => $driverIdForComplaint,
            ':entrepreneur_id' => $entrepreneurId ?: null,
            ':subject' => $subject,
            ':action_type' => 'strafe',
            ':status' => 'pruefen',
            ':created_by' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            ':employee_code' => mb_substr($employeeCode, 0, 40),
        ]);

        if ($driverMissingInFms) {
            $ok = 'Beschwerde wurde aufgenommen und als Backoffice-Prüffall markiert.';
        } else {
            $ok = 'Beschwerde wurde vorbereitet und in die Übersicht übernommen.';
        }
    } catch (Throwable $e) {
        $err = 'Fehler: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Beschwerde-Erfassung</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#f8f9fa}
    .wrap{max-width:980px;margin:0 auto;padding:16px}
    .code-input{width:42px;min-width:42px;max-width:42px;padding:.375rem .25rem;font-weight:600}
    .code-row{display:flex;gap:.4rem;flex-wrap:nowrap}
    .field-label-icon{display:inline-flex;align-items:center;gap:.4rem}
    .code-input.ok{border-color:#198754;box-shadow:0 0 0 .2rem rgba(25,135,84,.15)}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Beschwerde-Erfassung</h1>
      <p class="text-muted mb-3">Diese Eingabe erstellt eine vorbereitete Beschwerde für die Nachbearbeitung im Beschwerdemanagement.</p>

      <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($warn): ?><div class="alert alert-warning"><?= htmlspecialchars($warn, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-12 col-lg-8">
          <label class="form-label field-label-icon"><i class="bi bi-person-badge"></i> Fahrernummer</label>
          <div class="code-row" id="driverCodeWrap">
            <input class="form-control text-center code-input driver-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="driver_number_1" required aria-label="Fahrernummer Ziffer 1">
            <input class="form-control text-center code-input driver-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="driver_number_2" required aria-label="Fahrernummer Ziffer 2">
            <input class="form-control text-center code-input driver-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="driver_number_3" required aria-label="Fahrernummer Ziffer 3">
            <input class="form-control text-center code-input driver-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="driver_number_4" required aria-label="Fahrernummer Ziffer 4">
          </div>
          <div class="form-text">Bitte die <strong>Fahrernummer</strong> eingeben, nicht die Fahrzeugnummer.</div>
          <input type="hidden" name="driver_number" id="driverNumberHidden">
        </div>
        <div class="col-12 col-lg-8">
          <label class="form-label field-label-icon"><i class="bi bi-receipt"></i> Auftragsnummer</label>
          <div class="code-row" id="orderCodeWrap">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_1" required aria-label="Auftragsnummer Ziffer 1">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_2" required aria-label="Auftragsnummer Ziffer 2">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_3" required aria-label="Auftragsnummer Ziffer 3">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_4" required aria-label="Auftragsnummer Ziffer 4">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_5" required aria-label="Auftragsnummer Ziffer 5">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_6" required aria-label="Auftragsnummer Ziffer 6">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_7" required aria-label="Auftragsnummer Ziffer 7">
            <input class="form-control text-center code-input order-code" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="order_number_8" required aria-label="Auftragsnummer Ziffer 8">
          </div>
          <input type="hidden" name="order_number" id="orderNumberHidden">
        </div>

        <div class="col-12 col-lg-8">
          <label class="form-label field-label-icon"><i class="bi bi-person-vcard"></i> Mitarbeiter-Kennung</label>
          <input class="form-control" type="text" name="employee_code" maxlength="40" required placeholder="z. B. AB12">
          <div class="form-text">Wer erfasst die Beschwerde? Bitte persönliche Kennung eingeben.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Was ist passiert? *</label>
          <select class="form-select" name="incident_type" id="incidentType" required>
            <option value="">Bitte wählen</option>
            <option value="not_driven">Fahrer hat Auftrag angenommen, aber ist nicht hingefahren</option>
            <option value="wrong_passenger">Fahrer hat Auftrag angenommen, ist hingefahren, aber ohne Fahrgast / falscher Fahrgast weggefahren</option>
            <option value="cancelled">Fahrer hat Auftrag angenommen und diesen einfach storniert/abgeschlossen</option>
            <option value="other">Anderes (Freitext)</option>
          </select>
        </div>

        <div class="col-12" id="otherWrap" style="display:none;">
          <label class="form-label">Freitext für Sachverhalt *</label>
          <textarea class="form-control" name="other_text" rows="4" placeholder="Bitte Vorfall kurz und klar beschreiben"></textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit">Beschwerde vorbereiten</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  const incident = document.getElementById('incidentType');
  const otherWrap = document.getElementById('otherWrap');
  const toggleOther = () => {
    if (!incident || !otherWrap) return;
    otherWrap.style.display = incident.value === 'other' ? '' : 'none';
  };
  if (incident) {
    incident.addEventListener('change', toggleOther);
    toggleOther();
  }

  const driverInputs = Array.from(document.querySelectorAll('.driver-code'));
  const orderInputs = Array.from(document.querySelectorAll('.order-code'));
  const driverHidden = document.getElementById('driverNumberHidden');
  const orderHidden = document.getElementById('orderNumberHidden');
  const form = document.querySelector('form[method="post"]');

  const bindCodeInputs = (inputs, hidden, requiredLen) => {
    const sync = () => {
      if (!hidden) return '';
      const value = inputs.map(i => (i.value || '').replace(/\D/g, '').slice(0,1)).join('');
      hidden.value = value;
      const ok = value.length === requiredLen;
      inputs.forEach(i => i.classList.toggle('ok', ok));
      return value;
    };

    inputs.forEach((input, idx) => {
      input.addEventListener('input', () => {
        input.value = (input.value || '').replace(/\D/g, '').slice(0,1);
        if (input.value && idx < inputs.length - 1) inputs[idx + 1].focus();
        sync();
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && idx > 0) inputs[idx - 1].focus();
      });

      input.addEventListener('paste', (e) => {
        const pasted = (e.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0,requiredLen);
        if (!pasted) return;
        e.preventDefault();
        pasted.split('').forEach((ch, i) => { if (inputs[i]) inputs[i].value = ch; });
        const next = Math.min(pasted.length, inputs.length - 1);
        inputs[next].focus();
        sync();
      });
    });

    sync();
    return sync;
  };

  const syncDriver = bindCodeInputs(driverInputs, driverHidden, 4);
  const syncOrder = bindCodeInputs(orderInputs, orderHidden, 8);

  if (form) {
    form.addEventListener('submit', (e) => {
      const driverValue = syncDriver();
      const orderValue = syncOrder();
      if (driverValue.length !== 4) {
        e.preventDefault();
        alert('Bitte die Fahrernummer 4-stellig eingeben (nicht die Fahrzeugnummer).');
        driverInputs[0]?.focus();
        return;
      }
      if (orderValue.length !== 8) {
        e.preventDefault();
        alert('Bitte die Auftragsnummer 8-stellig eingeben.');
        orderInputs[0]?.focus();
      }
    });
  }
</script>
</body>
</html>
