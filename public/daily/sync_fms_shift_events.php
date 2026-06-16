<?php
ini_set("display_errors", 0);
error_reporting(E_ALL);

require_once __DIR__ . "/../../includes/db.php";

$pdo->exec("CREATE TABLE IF NOT EXISTS driver_shift_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    fahrer_nr INT NULL,
    shift_date DATE NOT NULL,
    shift_start DATETIME NULL,
    shift_end DATETIME NULL,
    first_occupied_at DATETIME NULL,
    last_occupied_at DATETIME NULL,
    source VARCHAR(30) NOT NULL DEFAULT \"fms\",
    source_shift_key VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_driver_shift_date (driver_id, shift_date, source_shift_key),
    KEY idx_shift_date (shift_date),
    KEY idx_driver_date (driver_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sql = "SELECT sfa.fahrer, sfa.anmeldung, sfa.abmeldung, f.FahrerID
        FROM sync_fahreranmeldung sfa
        JOIN Fahrer f ON (f.fms_alias = sfa.fahrer OR f.Fahrernummer = sfa.fahrer)
        WHERE sfa.abmeldung IS NOT NULL
          AND sfa.abmeldung >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$up = $pdo->prepare("INSERT INTO driver_shift_events
(driver_id, fahrer_nr, shift_date, shift_start, shift_end, first_occupied_at, last_occupied_at, source, source_shift_key)
VALUES (:driver_id,:fahrer_nr,:shift_date,:shift_start,:shift_end,:first_occ,:last_occ,'fms',:shift_key)
ON DUPLICATE KEY UPDATE
 shift_start=VALUES(shift_start),
 shift_end=VALUES(shift_end),
 updated_at=CURRENT_TIMESTAMP");

foreach ($rows as $r) {
    $start = $r["anmeldung"];
    $end = $r["abmeldung"];
    if (!$start || !$end) continue;
    $shiftDate = date("Y-m-d", strtotime($start));
    $shiftKey = (string)$r["fahrer"] . "_" . date("YmdHis", strtotime($start));

    // Erste/letzte Besetztschaltung aktuell nicht in DB vorhanden -> NULL.
    $up->execute([
        ":driver_id" => (int)$r["FahrerID"],
        ":fahrer_nr" => (int)$r["fahrer"],
        ":shift_date" => $shiftDate,
        ":shift_start" => $start,
        ":shift_end" => $end,
        ":first_occ" => null,
        ":last_occ" => null,
        ":shift_key" => $shiftKey,
    ]);
}

echo "OK: " . count($rows) . " Schichten verarbeitet\n";
