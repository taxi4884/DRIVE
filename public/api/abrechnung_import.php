<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB-Verbindung
require_once __DIR__ . '/../../includes/db.php';

// JSON empfangen
$data = json_decode(file_get_contents('php://input'), true);

// Sicherheitsprüfung (optional Token o.ä.)

foreach ($data as $entry) {
    $stmt = $pdo->prepare("
    INSERT IGNORE INTO sync_abrechnung (
			fahrer, kennung, zeitpunkt, zahlungsart, preis, auftragid, unternehmer
		) VALUES (
			:fahrer, :kennung, :zeitpunkt, :zahlungsart, :preis, :auftragid, :unternehmer
		)
	");

    $stmt->execute([
        ':fahrer'      => $entry['fahrer'],
        ':kennung'     => $entry['kennung'],
        ':zeitpunkt'   => $entry['zeitpunkt'],
        ':zahlungsart' => $entry['zahlungsart'],
        ':preis'       => $entry['preis'],
        ':auftragid'   => $entry['auftragid'],
        ':unternehmer' => $entry['unternehmer'],
    ]);
}

echo json_encode(['status' => '✅ Daten gespeichert']);
