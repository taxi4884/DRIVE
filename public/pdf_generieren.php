<?php
require('../fpdf/fpdf.php');
require_once '../includes/db.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Teilnehmerdaten abrufen
    $query = "SELECT vorname, nachname, strasse, hausnummer, postleitzahl, ort, geburtsdatum, handynummer, email, unternehmer FROM schulungsteilnehmer WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $id]);
    $teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teilnehmer) {
        die("Teilnehmer nicht gefunden");
    }

    // FPDF Initialisierung
    $pdf = new FPDF();
    $pdf->AddPage();

    // Farben und Schriftarten
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetFillColor(240, 240, 255);
    $pdf->SetTextColor(33, 33, 33);

    // Titel
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetFillColor(70, 130, 180); // Steel Blue
    $pdf->SetTextColor(255);
    $pdf->Cell(0, 15, utf8_decode('Teilnehmerinformationen'), 0, 1, 'C', true);
    $pdf->Ln(8);

    // Reset für normalen Text
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0);

    // Datenfelder
    $fields = [
        'Vorname' => $teilnehmer['vorname'],
        'Nachname' => $teilnehmer['nachname'],
        'Straße' => $teilnehmer['strasse'] . ' ' . $teilnehmer['hausnummer'],
        'PLZ / Ort' => $teilnehmer['postleitzahl'] . ' ' . $teilnehmer['ort'],
        'Geburtsdatum' => date('d.m.Y', strtotime($teilnehmer['geburtsdatum'])),
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

    // Ausgabe
    $filename = 'Teilnehmer_' . $teilnehmer['nachname'] . '_' . $teilnehmer['vorname'] . '.pdf';
    $pdf->Output('D', $filename);
    exit();
} else {
    die("Ungültige Anfrage");
}
