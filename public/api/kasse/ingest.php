<?php
declare(strict_types=1);
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/../../../includes/db.php";

$expectedToken = "07765cc24d3ade9ec29b1468de3552d6205ff223f6320630";
$token = $_SERVER["HTTP_X_BRIDGE_TOKEN"] ?? "";
if ($expectedToken !== "" && !hash_equals($expectedToken, $token)) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "unauthorized"]);
    exit;
}

$rawBody = file_get_contents("php://input") ?: "";
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "invalid_json"]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS kasse_raw_ingest (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(64) NOT NULL UNIQUE,
    programm_id VARCHAR(64) NOT NULL,
    message_id VARCHAR(64) NOT NULL,
    message_type VARCHAR(16) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS kasse_settlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(64) NOT NULL UNIQUE,
    programm_id VARCHAR(64) NOT NULL,
    message_id VARCHAR(64) NOT NULL,
    shift_identifier VARCHAR(128) NULL,
    feldverweis VARCHAR(128) NULL,
    amount_raw VARCHAR(64) NULL,
    amount_eur DECIMAL(12,2) NOT NULL DEFAULT 0,
    matched_umsatz_id INT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function getv(array $data, array $keys): string {
    foreach ($keys as $k) {
        if (array_key_exists($k, $data) && $data[$k] !== null) return trim((string)$data[$k]);
    }
    return "";
}

function normAmount(string $v): float {
    $v = trim($v);
    if ($v === "") return 0.0;
    if (preg_match('/[.,]/', $v)) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return (float)$v;
    }
    if (preg_match('/^-?\d+$/', $v)) {
        return ((float)$v) / 100.0;
    }
    return (float)$v;
}

$programmId = getv($data, ["programm_id", "programmId", "device", "device_id"]);
$messageId = getv($data, ["message_id", "messageId"]);
$messageType = strtolower(getv($data, ["message_type", "messageType"]));

$payload = $data;
if (isset($data['payload']) && is_array($data['payload'])) {
    $payload = $data['payload'];
}

$shiftIdentifier = getv($payload, ["SHIFTIDENTIFIER", "shiftidentifier", "shift_identifier", "schicht_id"]);
$feldverweis = getv($payload, ["FELDVERWEIS", "feldverweis", "field_ref"]);
$amountRaw = getv($payload, ["AMOUNT", "amount", "summe", "cash_amount"]);

if ($shiftIdentifier === "" && $feldverweis === "" && isset($data['raw']) && is_array($data['raw'])) {
    $joined = implode('|', array_map('strval', $data['raw']));
    if (preg_match('/SHIFTIDENTIFIER[:=]([^|]+)/i', $joined, $m)) $shiftIdentifier = trim($m[1]);
    if (preg_match('/FELDVERWEIS[:=]([^|]+)/i', $joined, $m)) $feldverweis = trim($m[1]);
    if (preg_match('/AMOUNT[:=]([^|]+)/i', $joined, $m)) $amountRaw = trim($m[1]);
}

$amountEur = normAmount($amountRaw);
$idempotencyKey = hash('sha256', $programmId . '|' . $messageId . '|' . $messageType . '|' . $shiftIdentifier . '|' . $feldverweis . '|' . $amountRaw);

$pdo->beginTransaction();
try {
    $stmtRaw = $pdo->prepare("INSERT IGNORE INTO kasse_raw_ingest
        (idempotency_key, programm_id, message_id, message_type, payload_json, received_at)
        VALUES (:key, :programm_id, :message_id, :message_type, :payload_json, NOW())");
    $stmtRaw->execute([
        ':key' => $idempotencyKey,
        ':programm_id' => $programmId,
        ':message_id' => $messageId,
        ':message_type' => $messageType,
        ':payload_json' => $rawBody,
    ]);

    $stmtSet = $pdo->prepare("INSERT IGNORE INTO kasse_settlements
        (idempotency_key, programm_id, message_id, shift_identifier, feldverweis, amount_raw, amount_eur, status, created_at)
        VALUES (:key, :programm_id, :message_id, :shift_identifier, :feldverweis, :amount_raw, :amount_eur, 'received', NOW())");
    $stmtSet->execute([
        ':key' => $idempotencyKey,
        ':programm_id' => $programmId,
        ':message_id' => $messageId,
        ':shift_identifier' => $shiftIdentifier,
        ':feldverweis' => $feldverweis,
        ':amount_raw' => $amountRaw,
        ':amount_eur' => $amountEur,
    ]);

    $matchedUmsatzId = null;
    $candidateIds = [];
    foreach ([$feldverweis, $shiftIdentifier] as $cand) {
        if ($cand !== '' && ctype_digit($cand)) $candidateIds[] = (int)$cand;
    }
    $candidateIds = array_values(array_unique($candidateIds));

    if ($amountEur > 0 && !empty($candidateIds)) {
        $in = implode(',', array_fill(0, count($candidateIds), '?'));
        $q = $pdo->prepare("SELECT UmsatzID FROM Umsatz WHERE UmsatzID IN ($in) ORDER BY UmsatzID DESC LIMIT 1");
        $q->execute($candidateIds);
        $matchedUmsatzId = $q->fetchColumn();

        if ($matchedUmsatzId) {
            $u = $pdo->prepare("UPDATE Umsatz
                SET EingezahltBetrag = IFNULL(EingezahltBetrag,0) + :betrag,
                    EingezahltAm = NOW(),
                    EingezahltVon = :von
                WHERE UmsatzID = :id");
            $u->execute([
                ':betrag' => $amountEur,
                ':von' => 'Kassenautomat ' . ($programmId !== '' ? $programmId : ''),
                ':id' => (int)$matchedUmsatzId,
            ]);

            $s = $pdo->prepare("UPDATE kasse_settlements SET status='applied', matched_umsatz_id=:uid, updated_at=NOW(), note='eingezahlt gebucht' WHERE idempotency_key=:key");
            $s->execute([':uid' => (int)$matchedUmsatzId, ':key' => $idempotencyKey]);
        } else {
            $s = $pdo->prepare("UPDATE kasse_settlements SET status='unmatched', updated_at=NOW(), note='kein UmsatzID-Match aus SHIFTIDENTIFIER/FELDVERWEIS' WHERE idempotency_key=:key");
            $s->execute([':key' => $idempotencyKey]);
        }
    }

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'idempotency_key' => $idempotencyKey,
        'message_type' => $messageType,
        'amount_eur' => $amountEur,
        'matched_umsatz_id' => $matchedUmsatzId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}
