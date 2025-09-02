<?php
// delete_umsatz.php

require_once '../includes/bootstrap.php'; // Stellt die PDO-Verbindung und weitere Einstellungen bereit.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Werte aus dem POST-Request abrufen
    $umsatzid = $_POST['umsatzid'] ?? null;
    $fahrer_id = $_POST['fahrer_id'] ?? null;

    // Überprüfen, ob die erforderlichen Parameter vorhanden sind
    if (!$umsatzid || !$fahrer_id) {
        die("Fehlende Parameter: Umsatz-ID und Fahrer-ID müssen übermittelt werden.");
    }

    // SQL-Statement zum Löschen des Umsatz-Eintrags
    $sql = "DELETE FROM Umsatz WHERE UmsatzID = :umsatzid";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':umsatzid' => $umsatzid
        ]);

        // Weiterleitung zurück zur Übersicht des entsprechenden Fahrers
        header("Location: fahrer_umsatz.php?fahrer_id=" . urlencode($fahrer_id));
        exit();
    } catch (PDOException $e) {
        die("Fehler beim Löschen des Umsatzes: " . $e->getMessage());
    }
} else {
    // Falls die Seite nicht per POST aufgerufen wurde, erfolgt eine Weiterleitung
    header("Location: fahrer_umsatz.php");
    exit();
}
