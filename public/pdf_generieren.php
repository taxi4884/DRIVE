<?php
require('../fpdf/fpdf.php');
require_once '../includes/db.php';

function utf8_to_iso88591($text) {
    $converted = mb_convert_encoding((string)$text, 'ISO-8859-1', 'UTF-8');
    return $converted === false ? '' : $converted;
}

function addTeilnehmerInfoSeite(FPDF $pdf, array $t): void
{
    $pdf->AddPage();
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_to_iso88591('Teilnehmerinformationen'), 0, 1, 'L');
    $pdf->Line(10, 20, 200, 20);
    $pdf->Ln(4);

    $geburtsdatum = '';
    if (!empty($t['geburtsdatum'])) {
        $ts = strtotime((string)$t['geburtsdatum']);
        $geburtsdatum = $ts ? date('d.m.Y', $ts) : (string)$t['geburtsdatum'];
    }

    $fields = [
        'Vorname'     => $t['vorname'] ?? '',
        'Nachname'    => $t['nachname'] ?? '',
        'Straße'      => trim(($t['strasse'] ?? '') . ' ' . ($t['hausnummer'] ?? '')),
        'PLZ / Ort'   => trim(($t['postleitzahl'] ?? '') . ' ' . ($t['ort'] ?? '')),
        'Geburtsdatum'=> $geburtsdatum,
        'Handynummer' => $t['handynummer'] ?? '',
        'Email'       => $t['email'] ?? '',
        'Unternehmer' => $t['unternehmer'] ?? '',
        'FMS-Nummer'  => $t['fms_fahrer_nr'] ?? '',
        'FMS-Code'    => $t['fms_anmeldecode'] ?? '',
    ];

    foreach ($fields as $label => $value) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(48, 8, utf8_to_iso88591($label . ':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_to_iso88591((string)$value), 0, 1, 'L');
    }
}

function addMerkblattSeite(FPDF $pdf, array $t): void
{
    $fahrerNr = (string)($t['fms_fahrer_nr'] ?? '');
    $fahrerCode = (string)($t['fms_anmeldecode'] ?? '');
    $logoPath = __DIR__ . '/images/4884-logo.png';

    $pdf->AddPage();
    $pdf->SetTextColor(0, 0, 0);

    // Schlichtes Header-Layout (druckfreundlich)
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(140, 10, utf8_to_iso88591('Willkommen bei Taxi 4884'), 0, 0, 'L');
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 162, 8, 35);
    }
    $pdf->Ln(12);
    $pdf->Line(10, 22, 200, 22);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_to_iso88591('Schön, dass du als Fahrer im Namen der 4884 - Ihr Funktaxi unterwegs bist.'));
    $pdf->MultiCell(0, 7, utf8_to_iso88591('Mit den folgenden Zugangsdaten kannst du dich im Fahrzeug anmelden.'));
    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_to_iso88591('Deine persönlichen Zugangsdaten'), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(55, 8, utf8_to_iso88591('Fahrernummer'), 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_to_iso88591($fahrerNr), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(55, 8, utf8_to_iso88591('Fahrercode / PIN'), 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_to_iso88591($fahrerCode), 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->MultiCell(0, 7, utf8_to_iso88591('Wichtig: Am Funkgerät ist der PIN identisch mit deinem Fahrercode.'));
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, utf8_to_iso88591('Anmeldung im Fahrzeug'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_to_iso88591('Im Anmeldebildschirm gibt es zwei Eingabefelder.'));
    $pdf->Ln(1);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, utf8_to_iso88591('1) Unternehmeranmeldung'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_to_iso88591('Hier meldest du das Fahrzeug bei der Zentrale an.'));
    // Unternehmerfeld bewusst leer
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 9, utf8_to_iso88591('4884fapp"____________"'), 1, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, utf8_to_iso88591('2) Fahreranmeldung'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_to_iso88591('Hier gibst du deinen persönlichen Fahrercode ein.'));
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 9, utf8_to_iso88591($fahrerCode !== '' ? $fahrerCode : 'DEIN FAHRERCODE'), 1, 1, 'C');
}

function addGestattungsvertrag(FPDF $pdf, array $t): void
{
    $geburtsdatum = '';
    if (!empty($t['geburtsdatum'])) {
        $ts = strtotime((string)$t['geburtsdatum']);
        $geburtsdatum = $ts ? date('d.m.Y', $ts) : (string)$t['geburtsdatum'];
    }

    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 9, utf8_to_iso88591('Gestattungsvertrag'), 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('Arial', '', 10);
    $head = "zwischen\n\n"
        . "4884 - Ihr Funktaxi\n"
        . "Älteste Leipziger Funktaxenzentrale GmbH\n"
        . "Lützner Straße 179\n"
        . "04179 Leipzig\n\n"
        . "- im Folgenden 4884 genannt -\n"
        . "und\n\n"
        . "dem Chauffeur / der Chauffeurin\n\n"
        . "Vorname, Name: " . ($t['vorname'] ?? '') . " " . ($t['nachname'] ?? '') . "\n"
        . "Geburtsdatum: " . $geburtsdatum . "\n"
        . "Straße, HsNr: " . trim(($t['strasse'] ?? '') . " " . ($t['hausnummer'] ?? '')) . "\n"
        . "PLZ, Wohnort: " . trim(($t['postleitzahl'] ?? '') . " " . ($t['ort'] ?? '')) . "\n"
        . "Handy: " . ($t['handynummer'] ?? '') . "\n"
        . "E-Mail: " . ($t['email'] ?? '') . "\n\n"
        . "- im Folgenden Chauffeur genannt -";
    $pdf->MultiCell(0, 6, utf8_to_iso88591($head));
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, utf8_to_iso88591('Präambel'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $prae = "4884 ist ein Unternehmen, das sich auf die Vermittlung von Fahraufträgen spezialisiert hat und"
        . " seinen Vertragspartnern auf hohem technischen und personellen Niveau zur Verfügung steht.\n"
        . "Die Fahr- und Funkdienstordnung in der jeweils gültigen Fassung wird Bestandteil dieses Vertrages.\n\n"
        . "Dies vorausgeschickt, vereinbaren die Parteien Folgendes:";
    $pdf->MultiCell(0, 6, utf8_to_iso88591($prae));
    $pdf->Ln(2);

    $paragraphs = [
        '§ 1 Teilnahme' => 'Mit Abschluss dieses Vertrages und nach erfolgreicher Teilnahme am Training kann der Chauffeur an der Fahrtenvermittlung teilnehmen.',
        '§ 2 Laufzeit' => 'Der Gestattungsvertrag wird für ein Jahr geschlossen und verlängert sich, sofern er nicht fristgerecht gekündigt wird.',
        '§ 3 Abtretung' => 'Die Abtretung von Rechten aus diesem Vertrag ist nur mit schriftlicher Zustimmung von 4884 zulässig.',
        '§ 4 Ordnung' => 'Die jeweils gültige Fahr- und Funkdienstordnung ist Bestandteil dieses Vertrages.',
    ];

    foreach ($paragraphs as $title => $text) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, utf8_to_iso88591($title), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, utf8_to_iso88591($text));
        $pdf->Ln(1);
    }

    $pdf->Ln(8);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, utf8_to_iso88591('Leipzig, den ____________________'), 0, 1, 'L');
    $pdf->Ln(10);
    $pdf->Cell(85, 6, utf8_to_iso88591('_____________________________'), 0, 0, 'L');
    $pdf->Cell(20, 6, '', 0, 0);
    $pdf->Cell(85, 6, utf8_to_iso88591('_____________________________'), 0, 1, 'L');
    $pdf->Cell(85, 6, utf8_to_iso88591('Chauffeur'), 0, 0, 'L');
    $pdf->Cell(20, 6, '', 0, 0);
    $pdf->Cell(85, 6, utf8_to_iso88591('4884'), 0, 1, 'L');
}

if (!isset($_GET['id'])) {
    die('Ungültige Anfrage');
}

$id = (int)$_GET['id'];
$query = "SELECT vorname, nachname, strasse, hausnummer, postleitzahl, ort, geburtsdatum, handynummer, email, unternehmer, fms_fahrer_nr, fms_anmeldecode FROM schulungsteilnehmer WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute([':id' => $id]);
$teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teilnehmer) {
    die('Teilnehmer nicht gefunden');
}

$pdf = new FPDF();
addTeilnehmerInfoSeite($pdf, $teilnehmer);
addMerkblattSeite($pdf, $teilnehmer);

$filename = 'Teilnehmer_' . ($teilnehmer['nachname'] ?? 'Teilnehmer') . '_' . ($teilnehmer['vorname'] ?? '') . '.pdf';
$pdf->Output('D', $filename);
exit();
