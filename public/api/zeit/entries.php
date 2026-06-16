<?php
declare(strict_types=1);
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/../../../includes/db.php";
$limit = max(1, min(200, (int)($_GET["limit"] ?? 50)));
$stmt = $pdo->prepare("SELECT id, programm_id, programm_name, programm_version, message_type, message_id, requires_ack, source_received_at, created_at
                      FROM zeit_entries ORDER BY id DESC LIMIT :lim");
$stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
$stmt->execute();
echo json_encode(["ok" => true, "items" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
