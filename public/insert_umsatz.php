<?php
// insert_umsatz.php

require_once '../includes/bootstrap.php'; // Stellt die PDO-Verbindung und weitere notwendige Einstellungen bereit.

// Sicherstellen, dass das Formular per POST abgesendet wurde.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingaben aus dem Formular abrufen und standardmäßig auf 0 setzen, falls keine Werte übermittelt wurden.
    $fahrer_id         = $_POST['fahrer_id'] ?? null;
    $datum             = $_POST['datum'] ?? null;
    $taxameter         = $_POST['taxameter'] ?? 0;
    $ohnetaxameter     = $_POST['ohnetaxameter'] ?? 0;
    $kartenzahlung     = $_POST['kartenzahlung'] ?? 0;
    $rechnungsfahrten  = $_POST['rechnungsfahrten'] ?? 0;
    $krankenfahrten    = $_POST['krankenfahrten'] ?? 0;
    $gutscheine        = $_POST['gutscheine'] ?? 0;
    $alita             = $_POST['alita'] ?? 0;
    $tankwaschen       = $_POST['tankwaschen'] ?? 0;
    $sonstige          = $_POST['sonstige'] ?? 0;

    // Überprüfen, ob die Pflichtfelder vorhanden sind.
    if (!$fahrer_id || !$datum) {
        die('Fehlende Pflichtfelder: Fahrer und Datum müssen angegeben werden.');
    }

    // SQL-Query zum Einfügen eines neuen Umsatz-Eintrags.
    $sql = "INSERT INTO Umsatz (
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
                SonstigeAusgaben
            ) VALUES (
                :fahrer_id, 
                :datum, 
                :taxameter, 
                :ohnetaxameter, 
                :kartenzahlung, 
                :rechnungsfahrten, 
                :krankenfahrten, 
                :gutscheine, 
                :alita, 
                :tankwaschen, 
                :sonstige
            )";

    // Vorbereitung und Ausführung der SQL-Anweisung mit PDO.
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':fahrer_id'        => $fahrer_id,
            ':datum'            => $datum,
            ':taxameter'        => $taxameter,
            ':ohnetaxameter'    => $ohnetaxameter,
            ':kartenzahlung'    => $kartenzahlung,
            ':rechnungsfahrten' => $rechnungsfahrten,
            ':krankenfahrten'   => $krankenfahrten,
            ':gutscheine'       => $gutscheine,
            ':alita'            => $alita,
            ':tankwaschen'      => $tankwaschen,
            ':sonstige'         => $sonstige
        ]);

        // Nach erfolgreichem Einfügen erfolgt eine Weiterleitung zur Umsatz-Übersicht für den entsprechenden Fahrer.
        header("Location: fahrer_umsatz.php?fahrer_id=" . urlencode($fahrer_id));
        exit();
    } catch (PDOException $e) {
        die("Fehler beim Einfügen des Umsatzes: " . $e->getMessage());
    }
} else {
    // Falls die Seite nicht per POST aufgerufen wurde, wird zur Übersicht weitergeleitet.
    header("Location: umsatz.php");
    exit();
}
