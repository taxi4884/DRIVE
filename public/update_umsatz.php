<?php
// public/update_umsatz.php

require_once '../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formularfelder auslesen
    $umsatzid         = $_POST['umsatzid'] ?? null;
    $fahrer_id        = $_POST['fahrer_id'] ?? null;
    $datum            = $_POST['datum'] ?? null;
    $taxameter        = $_POST['taxameter'] ?? 0;
    $ohnetaxameter    = $_POST['ohnetaxameter'] ?? 0;
    $kartenzahlung    = $_POST['kartenzahlung'] ?? 0;
    $rechnungsfahrten = $_POST['rechnungsfahrten'] ?? 0;
    $krankenfahrten   = $_POST['krankenfahrten'] ?? 0;
    $gutscheine       = $_POST['gutscheine'] ?? 0;
    $alita            = $_POST['alita'] ?? 0;
    $tankwaschen      = $_POST['tankwaschen'] ?? 0;
    $sonstige         = $_POST['sonstige'] ?? 0;
	$notiz = $_POST['notiz'] ?? null;

    // Pflichtfelder überprüfen
    if (!$umsatzid || !$fahrer_id || !$datum) {
        die('Fehlende Pflichtfelder: Umsatz-ID, Fahrer und Datum müssen angegeben werden.');
    }

    // Alte Daten laden für Änderungsverfolgung
    $stmtAlt = $pdo->prepare("SELECT * FROM Umsatz WHERE UmsatzID = ?");
    $stmtAlt->execute([$umsatzid]);
    $alt = $stmtAlt->fetch(PDO::FETCH_ASSOC);

    if (!$alt) {
        die("Umsatz-Datensatz nicht gefunden.");
    }

    // Änderungen vergleichen und protokollieren
    $felder = [
        'Datum'            => $datum,
        'TaxameterUmsatz'  => $taxameter,
        'OhneTaxameter'    => $ohnetaxameter,
        'Kartenzahlung'    => $kartenzahlung,
        'Rechnungsfahrten' => $rechnungsfahrten,
        'Krankenfahrten'   => $krankenfahrten,
        'Gutscheine'       => $gutscheine,
        'Alita'            => $alita,
        'TankenWaschen'    => $tankwaschen,
        'SonstigeAusgaben' => $sonstige,
		'Notiz'            => $notiz
    ];

    foreach ($felder as $feld => $neuWert) {
        $altWert = $alt[$feld];

        // Nur protokollieren, wenn sich der Wert tatsächlich ändert
        if (number_format((float)$altWert, 2, '.', '') !== number_format((float)$neuWert, 2, '.', '')) {
            $stmtLog = $pdo->prepare("
                INSERT INTO Umsatz_Aenderungen (UmsatzID, Benutzer, Feldname, AlterWert, NeuerWert)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtLog->execute([
                $umsatzid,
                $_SESSION['username'] ?? 'unbekannt',
                $feld,
                $altWert,
                $neuWert
            ]);
        }
    }

    // SQL-Abfrage zum Aktualisieren des Umsatz-Eintrags
    $sql = "UPDATE Umsatz SET
                Datum = :datum,
                TaxameterUmsatz = :taxameter,
                OhneTaxameter = :ohnetaxameter,
                Kartenzahlung = :kartenzahlung,
                Rechnungsfahrten = :rechnungsfahrten,
                Krankenfahrten = :krankenfahrten,
                Gutscheine = :gutscheine,
                Alita = :alita,
                TankenWaschen = :tankwaschen,
                SonstigeAusgaben = :sonstige,
				Notiz = :notiz
            WHERE UmsatzID = :umsatzid";

    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':datum'            => $datum,
            ':taxameter'        => $taxameter,
            ':ohnetaxameter'    => $ohnetaxameter,
            ':kartenzahlung'    => $kartenzahlung,
            ':rechnungsfahrten' => $rechnungsfahrten,
            ':krankenfahrten'   => $krankenfahrten,
            ':gutscheine'       => $gutscheine,
            ':alita'            => $alita,
            ':tankwaschen'      => $tankwaschen,
            ':sonstige'         => $sonstige,
			':notiz'            => $notiz,
            ':umsatzid'         => $umsatzid
        ]);

        // Weiterleitung zurück zur Übersicht des entsprechenden Fahrers
        header("Location: fahrer_umsatz.php?fahrer_id=" . urlencode($fahrer_id));
        exit();
    } catch (PDOException $e) {
        die("Fehler beim Aktualisieren des Umsatzes: " . $e->getMessage());
    }
} else {
    // Wenn die Datei nicht per POST aufgerufen wurde, zur Übersicht weiterleiten.
    header("Location: umsatz.php");
    exit();
}
