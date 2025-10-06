<?php
require('../fpdf/fpdf.php');
require_once '../includes/db.php';

// Bestätigte Teilnehmer abrufen
$query = "
    SELECT vorname, nachname, strasse, hausnummer, postleitzahl, ort, geburtsdatum, handynummer, email, unternehmer 
    FROM schulungsteilnehmer 
    WHERE rueckmeldung_status = 1 
    ORDER BY nachname, vorname
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$teilnehmerListe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDF initialisieren
$pdf = new FPDF();
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetFillColor(240, 240, 255);
$pdf->SetTextColor(33, 33, 33);

foreach ($teilnehmerListe as $teilnehmer) {
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetFillColor(70, 130, 180); // Steel Blue
    $pdf->SetTextColor(255);
    $pdf->Cell(0, 15, utf8_decode('Teilnehmerinformationen'), 0, 1, 'C', true);
    $pdf->Ln(8);

    // Teilnehmerdaten
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0);
    
    $fields = [
        'Vorname' => $teilnehmer['vorname'],
        'Nachname' => $teilnehmer['nachname'],
        'Straße' => $teilnehmer['strasse'] . ' ' . $teilnehmer['hausnummer'],
        'PLZ / Ort' => $teilnehmer['postleitzahl'] . ' ' . $teilnehmer['ort'],
        'Geburtsdatum' => date('d.m.y', strtotime($teilnehmer['geburtsdatum'])),
        'Handynummer' => $teilnehmer['handynummer'],
        'Email' => $teilnehmer['email'],
        'Unternehmer' => $teilnehmer['unternehmer']
    ];

    foreach ($fields as $label => $value) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(50, 10, utf8_decode($label . ':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 10, utf8_decode($value), 0, 1, 'L');
        $pdf->Ln(1);
    }
}

// Dateiname z. B. mit Datum
$filename = 'Schulung_Alle_Bestaetigten_' . date('d-m-y') . '.pdf';
$pdf->Output('D', $filename);
exit();
