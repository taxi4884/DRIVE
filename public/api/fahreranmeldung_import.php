<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../includes/db.php');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    echo json_encode(["status" => "❌ Ungültiges JSON"]);
    exit;
}

foreach ($data as $entry) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sync_fahreranmeldung (
                unternehmer, kennung, anmeldung, abmeldung, stunden, fahrer, fahrzeugflotte
            ) VALUES (
                :unternehmer, :kennung, :anmeldung, :abmeldung, :stunden, :fahrer, :fahrzeugflotte
            )
            ON DUPLICATE KEY UPDATE
                abmeldung = VALUES(abmeldung),
                stunden = VALUES(stunden),
                fahrzeugflotte = VALUES(fahrzeugflotte)
        ");

        $stmt->execute([
            ':unternehmer'     => $entry['unternehmer'] ?? null,
            ':kennung'         => $entry['kennung'] ?? null,
            ':anmeldung'       => $entry['anmeldung'] ?? null,
            ':abmeldung'       => $entry['abmeldung'] ?? null,
            ':stunden'         => $entry['stunden'] ?? null,
            ':fahrer'          => $entry['fahrer'] ?? null,
            ':fahrzeugflotte'  => $entry['fahrzeugflotte'] ?? null,
        ]);

    } catch (Exception $e) {
        file_put_contents(__DIR__ . "/../logs/fahreranmeldung_error.log", 
            date("c") . " – ❌ Fehler: " . $e->getMessage() . "\\n", 
            FILE_APPEND);
    }
}

echo json_encode(["status" => "✅ Daten gespeichert"]);
