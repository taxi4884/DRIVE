<?php
require_once '../includes/head.php';
require('../fpdf/fpdf.php');
require_once '../includes/date_utils.php';

ob_start(); // Output Buffering starten

// Fahrer-ID und Zeitraum aus den GET-Parametern abrufen
if (!isset($_GET['fahrer_id']) || empty($_GET['fahrer_id'])) {
    die('Keine Fahrer-ID übergeben!');
}

$fahrer_id = $_GET['fahrer_id'];
$jahr = !empty($start_date) ? date('Y', strtotime($start_date)) : date('Y');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fahrer-Daten abrufen
$stmtFahrer = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS Name, Urlaubstage FROM Fahrer WHERE FahrerID = ?");
$stmtFahrer->execute([$fahrer_id]);
$fahrer = $stmtFahrer->fetch(PDO::FETCH_ASSOC);

if (!$fahrer) {
    die('Fahrer nicht in der Datenbank gefunden!');
}

// genommenen Jahresurlaub abfragen
$stmtGenommenerUrlaub = $pdo->prepare("
    SELECT COALESCE(SUM(
        CASE 
            WHEN enddatum <= ? THEN DATEDIFF(enddatum, startdatum) + 1
            ELSE DATEDIFF(?, startdatum) + 1
        END
    ), 0) AS genommenerUrlaub
    FROM FahrerAbwesenheiten 
    WHERE FahrerID = ? 
    AND abwesenheitsart = 'Urlaub' 
    AND YEAR(startdatum) = ? 
    AND startdatum <= ?
");

echo "Debugging: FahrerID: $fahrer_id, Jahr: $jahr, Enddatum: $end_date<br>";

$stmtGenommenerUrlaub->execute([$end_date, $end_date, $fahrer_id, $jahr, $end_date]);
$genommenerUrlaub = (int) ($stmtGenommenerUrlaub->fetchColumn() ?? 0);  // Explizite Typumwandlung!

echo "Genommener Urlaub laut SQL: $genommenerUrlaub";

// Umsätze abrufen
$stmtUmsatz = $pdo->prepare("
    SELECT 
        DATE_FORMAT(Datum, '%d.%m.%Y') AS Datum,
        TaxameterUmsatz,
        OhneTaxameter,
        Kartenzahlung,
        Rechnungsfahrten,
        Krankenfahrten,
        Gutscheine,
        Alita,
        TankenWaschen,
        SonstigeAusgaben
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
    ORDER BY Datum ASC
");
$stmtUmsatz->execute([$fahrer_id, $start_date, $end_date]);
$umsatzDaten = $stmtUmsatz->fetchAll(PDO::FETCH_ASSOC);

// Gesamtumsatz berechnen
$stmtGesamt = $pdo->prepare("
    SELECT SUM(TaxameterUmsatz + OhneTaxameter) AS GesamtUmsatz
    FROM Umsatz
    WHERE FahrerID = ? AND Datum BETWEEN ? AND ?
");
$stmtGesamt->execute([$fahrer_id, $start_date, $end_date]);
$gesamtUmsatz = $stmtGesamt->fetchColumn();

// Gesamtsummen initialisieren
$gesamtSummen = [
    'TaxameterUmsatz' => 0,
    'OhneTaxameter' => 0,
    'Kartenzahlung' => 0,
    'Rechnungsfahrten' => 0,
    'Krankenfahrten' => 0,
    'Gutscheine' => 0,
    'Alita' => 0,
    'TankenWaschen' => 0,
    'SonstigeAusgaben' => 0
];

// Umsätze summieren
foreach ($umsatzDaten as $eintrag) {
    foreach ($gesamtSummen as $key => &$summe) {
        $summe += $eintrag[$key] ?? 0;
    }
	unset($summe);
}

// Krankheits- und Urlaubsstatus mit Zeitraum abrufen
try {
    // Krankheiten im Zeitraum gruppiert nach Grund mit Min/Max-Daten
    $stmtKrank = $pdo->prepare("
        SELECT grund, MIN(startdatum) AS start, MAX(enddatum) AS ende, COUNT(*) as count
        FROM FahrerAbwesenheiten
        WHERE FahrerID = ? AND abwesenheitsart = 'Krankheit'
          AND ((startdatum BETWEEN ? AND ?) OR (enddatum BETWEEN ? AND ?))
        GROUP BY grund
    ");
    $stmtKrank->execute([$fahrer_id, $start_date, $end_date, $start_date, $end_date]);
    $krankDetails = $stmtKrank->fetchAll(PDO::FETCH_ASSOC);

    // Urlaube im Zeitraum gruppiert nach Grund mit Min/Max-Daten
    $stmtUrlaub = $pdo->prepare("
        SELECT grund, MIN(startdatum) AS start, MAX(enddatum) AS ende, COUNT(*) as count
        FROM FahrerAbwesenheiten
        WHERE FahrerID = ? AND abwesenheitsart = 'Urlaub'
          AND ((startdatum BETWEEN ? AND ?) OR (enddatum BETWEEN ? AND ?))
        GROUP BY grund
    ");
    $stmtUrlaub->execute([$fahrer_id, $start_date, $end_date, $start_date, $end_date]);
    $urlaubDetails = $stmtUrlaub->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $krankDetails = [];
    $urlaubDetails = [];
}

$werktage = workdaysBetween($start_date, $end_date);
$startDatumFormat = date('d.m.Y', strtotime($start_date));
$endDatumFormat = date('d.m.Y', strtotime($end_date));

// PDF erstellen
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Überschrift
$pdf->Cell(0, 10, utf8_decode("Umsätze des Fahrers: " . $fahrer['Name']), 0, 1, 'C');
$pdf->Ln(5);

// Gesamtumsatz
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Gesamtumsatz: " . number_format($gesamtUmsatz, 2, ',', '.') . " Euro", 0, 1);
$pdf->Ln(5);

// Krankheits- und Urlaubsdaten als Tabelle formatieren
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 10, "Krankheit", 0, 0, 'C');
$pdf->Cell(95, 10, "Urlaub", 0, 1, 'C');

// Setzt die Schrift für den Tabelleninhalt
$pdf->SetFont('Arial', '', 10);

// Definiere die maximale Anzahl der Zeilen basierend auf der längeren Liste
$maxRows = max(count($krankDetails), count($urlaubDetails));
for ($i = 0; $i < $maxRows; $i++) {
    // Krankheitsdaten
    if (isset($krankDetails[$i])) {
        $detail = $krankDetails[$i];
        $krankStart = date('d.m.Y', strtotime($detail['start']));
        $krankEnde = date('d.m.Y', strtotime($detail['ende']));
        $days = (strtotime($detail['ende']) - strtotime($detail['start'])) / (60 * 60 * 24) + 1;
        $krankText = "{$detail['grund']} ({$krankStart} - {$krankEnde}, {$days} Tage)";
    } else {
        $krankText = " - ";
    }

    // Urlaubsdaten
    if (isset($urlaubDetails[$i])) {
        $detail = $urlaubDetails[$i];
        $urlaubStart = date('d.m.Y', strtotime($detail['start']));
        $urlaubEnde = date('d.m.Y', strtotime($detail['ende']));
        $days = (strtotime($detail['ende']) - strtotime($detail['start'])) / (60 * 60 * 24) + 1;
        $urlaubText = "{$detail['grund']} ({$urlaubStart} - {$urlaubEnde}, {$days} Tage)";
    } else {
        $urlaubText = " - ";
    }

    // Zeile in die Tabelle einfügen
    $pdf->Cell(95, 8, utf8_decode($krankText), 0, 0);
    $pdf->Cell(95, 8, utf8_decode($urlaubText), 0, 1);
}

// Gesamter Urlaubsanspruch und genommener Urlaub in die letzte Zeile der Tabelle setzen
$pdf->Cell(95, 8, "", 0, 0); // Leere Spalte für Ausrichtung
$pdf->Cell(95, 8, "Gesamtanspruch: " . $fahrer['Urlaubstage'] . " Tage", 0, 1, 'R');
$pdf->Cell(95, 8, "", 0, 0);
$pdf->Cell(95, 8, "Bereits genommen: " . $genommenerUrlaub . " Tage", 0, 1, 'R');

$pdf->Ln(5);


// Tabellenüberschriften
$colWidths = [20, 20, 20, 20, 20, 20, 20, 20, 20 ,20]; // Kleinere Spaltenbreiten
$pdf->SetLineWidth(0.1);
$pdf->SetFont('Arial', 'B', 9); // Kleinere Tabellen-Header
$pdf->SetFillColor(200, 200, 200); // Hellgraue Hintergrundfarbe für Header
$pdf->Cell($colWidths[0], 8, "Datum", 1, 0, 'C', true);
$pdf->Cell($colWidths[1], 8, "Taxa", 1, 0, 'C', true);
$pdf->Cell($colWidths[2], 8, "Ohne Taxa", 1, 0, 'C', true);
$pdf->Cell($colWidths[3], 8, "Karte", 1, 0, 'C', true);
$pdf->Cell($colWidths[4], 8, "Rechnung", 1, 0, 'C', true);
$pdf->Cell($colWidths[5], 8, "Kranken", 1, 0, 'C', true);
$pdf->Cell($colWidths[6], 8, "Gutscheine", 1, 0, 'C', true);
$pdf->Cell($colWidths[7], 8, "Alita", 1, 0, 'C', true);
$pdf->Cell($colWidths[8], 8, "Tanken", 1, 0, 'C', true);
$pdf->Cell($colWidths[9], 8, "sonstiges", 1, 1, 'C', true);

// Umsätze
$pdf->SetFont('Arial', '', 9);
foreach ($umsatzDaten as $eintrag) {
    $gesamtProTag = ($eintrag['TaxameterUmsatz'] ?? 0) + ($eintrag['OhneTaxameter'] ?? 0);
    
    // Normale Umsatz-Zeile
    $pdf->Cell($colWidths[0], 8, $eintrag['Datum'], 1, 0, 'C');
    $pdf->Cell($colWidths[1], 8, ($eintrag['TaxameterUmsatz'] ?? 0) == 0 ? '-' : number_format($eintrag['TaxameterUmsatz'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Cell($colWidths[2], 8, ($eintrag['OhneTaxameter'] ?? 0) == 0 ? '-' : number_format($eintrag['OhneTaxameter'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Cell($colWidths[3], 8, ($eintrag['Kartenzahlung'] ?? 0) == 0 ? '-' : number_format($eintrag['Kartenzahlung'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Cell($colWidths[4], 8, ($eintrag['Rechnungsfahrten'] ?? 0) == 0 ? '-' : number_format($eintrag['Rechnungsfahrten'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Cell($colWidths[5], 8, ($eintrag['Krankenfahrten'] ?? 0) == 0 ? '-' : number_format($eintrag['Krankenfahrten'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Cell($colWidths[6], 8, ($eintrag['Gutscheine'] ?? 0) == 0 ? '-' : number_format($eintrag['Gutscheine'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Cell($colWidths[7], 8, ($eintrag['Alita'] ?? 0) == 0 ? '-' : number_format($eintrag['Alita'], 2, ',', '.'), 1, 0, 'C');
	$pdf->Cell($colWidths[8], 8, ($eintrag['TankenWaschen'] ?? 0) == 0 ? '-' : number_format($eintrag['TankenWaschen'], 2, ',', '.'), 1, 0, 'C');
	$pdf->Cell($colWidths[9], 8, ($eintrag['SonstigeAusgaben'] ?? 0) == 0 ? '-' : number_format($eintrag['SonstigeAusgaben'], 2, ',', '.'), 1, 0, 'C');
    $pdf->Ln();

    // Zusätzliche Zeile für Gesamtbetrag
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($colWidths[0], 8, "Gesamt", 1, 0, 'C');
    $pdf->Cell($colWidths[1] + $colWidths[2], 8, number_format($gesamtProTag, 2, ',', '.') . " Euro", 1, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
}

// Gesamtsummen in die Tabelle einfügen
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220); // Hellgraue Hintergrundfarbe für die Summenzeile
$pdf->Cell($colWidths[0], 8, "Summe", 1, 0, 'C', true);

$i = 1; // Startet ab 1, da Datum die erste Spalte ist
foreach ($gesamtSummen as $key => $summe) {
    $pdf->Cell($colWidths[$i], 8, ($summe == 0 ? '-' : number_format($summe, 2, ',', '.')), 1, 0, 'C', true);
    $i++;
}
$pdf->Ln();

ob_end_clean(); // Output Buffering beenden
$pdf->Output('I', 'Fahrer_Umsatz_' . $fahrer_id . '.pdf');
?>
