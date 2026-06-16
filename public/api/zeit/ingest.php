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

$messageId = (string)($data["message_id"] ?? "");
$programmId = (string)($data["programm_id"] ?? "");
$messageType = (string)($data["message_type"] ?? "");
$idempotencyKey = hash("sha256", $programmId . "|" . $messageId . "|" . $messageType . "|" . ($data["received_at"] ?? ""));

$pdo->beginTransaction();
try {
    $stmtRaw = $pdo->prepare("INSERT IGNORE INTO zeit_raw_ingest
        (idempotency_key, programm_id, message_id, message_type, payload_json, received_at)
        VALUES (:key, :programm_id, :message_id, :message_type, :payload_json, NOW())");
    $stmtRaw->execute([
        ":key" => $idempotencyKey,
        ":programm_id" => $programmId,
        ":message_id" => $messageId,
        ":message_type" => $messageType,
        ":payload_json" => $rawBody,
    ]);

    $stmtEntry = $pdo->prepare("INSERT IGNORE INTO zeit_entries
        (idempotency_key, programm_id, programm_name, programm_version, message_type, message_id, requires_ack, source_received_at, created_at)
        VALUES (:key, :programm_id, :programm_name, :programm_version, :message_type, :message_id, :requires_ack, :source_received_at, NOW())");
    $stmtEntry->execute([
        ":key" => $idempotencyKey,
        ":programm_id" => $programmId,
        ":programm_name" => (string)($data["programm_name"] ?? ""),
        ":programm_version" => (string)($data["programm_version"] ?? ""),
        ":message_type" => $messageType,
        ":message_id" => $messageId,
        ":requires_ack" => (string)($data["requires_ack"] ?? ""),
        ":source_received_at" => (string)($data["received_at"] ?? ""),
    ]);

    $pdo->commit();
    echo json_encode(["ok" => true, "idempotency_key" => $idempotencyKey]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
