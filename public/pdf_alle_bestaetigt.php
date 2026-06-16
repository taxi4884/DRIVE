<?php
require('../fpdf/fpdf.php');
require_once '../includes/db.php';

if (!method_exists('FPDF', 'RoundedRect')) {
    class PDF_Extended extends FPDF {
        function RoundedRect($x, $y, $w, $h, $r, $corners = '1234', $style = '') {
            $k = $this->k;
            $hp = $this->h;
            if($style=='F') $op='f';
            elseif($style=='FD' || $style=='DF') $op='B';
            else $op='S';
            $MyArc = 4/3 * (sqrt(2) - 1);
            $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
            $xc = $x+$w-$r ;
            $yc = $y+$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-$y)*$k ));
            if (strpos($corners, '2')===false)
                $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$y)*$k));
            else
                $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
            $xc = $x+$w-$r ;
            $yc = $y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
            if (strpos($corners, '3')===false)
                $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-($y+$h))*$k));
            else
                $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
            $xc = $x+$r ;
            $yc = $y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
            if (strpos($corners, '4')===false)
                $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-($y+$h))*$k));
            else
                $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
            $xc = $x+$r ;
            $yc = $y+$r;
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
            if (strpos($corners, '1')===false)
                $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$y)*$k ));
            else
                $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
            $this->_out($op);
        }
        function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
            $h = $this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
                $x1*$this->k, ($h-$y1)*$this->k,
                $x2*$this->k, ($h-$y2)*$this->k,
                $x3*$this->k, ($h-$y3)*$this->k));
        }
    }
}


function addWillkommenSeite($pdf, $teilnehmer)
{
    $fahrerNr = (string)($teilnehmer['fms_fahrer_nr'] ?? '');
    $fahrerCode = (string)($teilnehmer['fms_anmeldecode'] ?? '');
    $unternehmerNummer = '1200';
    $logoPath = __DIR__ . '/images/4884-logo.png';
    $vollname = trim((string)($teilnehmer['vorname'] ?? '') . ' ' . (string)($teilnehmer['nachname'] ?? ''));

    $pdf->AddPage();

    // Kopfbereich
    $pdf->SetFillColor(70, 130, 180);
    $pdf->Rect(0, 0, 210, 30, 'F');

    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 155, 6, 42);
    }

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetXY(12, 9);
    $pdf->Cell(120, 10, utf8_decode('Willkommen bei Taxi 4884'), 0, 1, 'L');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetY(38);
    $pdf->SetFont('Arial', 'B', 13);
    $ansprache = $vollname !== '' ? ('Hallo ' . $vollname . ',') : 'Hallo,';
    $pdf->Cell(0, 8, utf8_decode($ansprache), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $einleitung = $vollname !== ''
        ? ('schön, dass du als Fahrer im Namen der 4884 - Ihr Funktaxi unterwegs bist, ' . $vollname . '.')
        : 'schön, dass du als Fahrer im Namen der 4884 - Ihr Funktaxi unterwegs bist.';
    $pdf->MultiCell(0, 7, utf8_decode($einleitung));
    $pdf->Ln(1);
    $pdf->MultiCell(0, 7, utf8_decode('Mit den folgenden Zugangsdaten kannst du dich im Fahrzeug anmelden.'));

    // Zugangsdaten-Box
    $pdf->Ln(4);
    $x = 12; $y = $pdf->GetY(); $w = 186;
    $pdf->SetFillColor(245, 250, 255);
    $pdf->SetDrawColor(180, 205, 230);
    $pdf->RoundedRect($x, $y, $w, 38, 2, '1234', 'DF');

    $pdf->SetXY($x + 4, $y + 4);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_decode('Deine persönlichen Zugangsdaten'), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 12);
    $pdf->SetX($x + 4);
    $pdf->Cell(58, 8, utf8_decode('Fahrernummer'), 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_decode($fahrerNr), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 12);
    $pdf->SetX($x + 4);
    $pdf->Cell(58, 8, utf8_decode('Fahrercode / PIN'), 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_decode($fahrerCode), 0, 1, 'L');

    $pdf->SetY($y + 42);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(160, 70, 0);
    $pdf->MultiCell(0, 7, utf8_decode('Wichtig: Am Funkgerät ist der PIN identisch mit deinem Fahrercode.'));

    // Anleitung
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, utf8_decode('Anmeldung im Fahrzeug'), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_decode('Im Anmeldebildschirm gibt es zwei Eingabefelder.'));
    $pdf->Ln(1);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, utf8_decode('1) Unternehmeranmeldung'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_decode('Hier meldest du das Fahrzeug bei der Zentrale an.'));
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(0, 9, utf8_decode('4884fapp"' . $unternehmerNummer . '"'), 1, 1, 'C', true);

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, utf8_decode('2) Fahreranmeldung'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, utf8_decode('Hier gibst du deinen persönlichen Fahrercode ein.'));
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(0, 9, utf8_decode($fahrerCode !== '' ? $fahrerCode : 'DEIN FAHRERCODE'), 1, 1, 'C', true);
}


$terminId = filter_input(INPUT_GET, 'termin_id', FILTER_VALIDATE_INT);
$terminDatum = null;

if ($terminId) {
    $terminStmt = $pdo->prepare("SELECT termin FROM schulungstermine WHERE id = :id");
    $terminStmt->execute([':id' => $terminId]);
    $terminDatum = $terminStmt->fetchColumn();
}

// Bestätigte Teilnehmer abrufen
$query = "
    SELECT vorname, nachname, strasse, hausnummer, postleitzahl, ort, geburtsdatum, handynummer, email, unternehmer, fms_fahrer_nr, fms_anmeldecode
      FROM schulungsteilnehmer
     WHERE schulungstermin_id IS NOT NULL
       AND (rueckmeldung_status = 1 OR rueckmeldung_status IS NULL)
";
$params = [];

if ($terminId) {
    $query .= " AND schulungstermin_id = :termin_id";
    $params[':termin_id'] = $terminId;
}

$query .= " ORDER BY nachname, vorname";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teilnehmerListe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDF initialisieren
$pdf = new PDF_Extended();
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
        'Unternehmer' => $teilnehmer['unternehmer'],
        'FMS-Nummer' => ($teilnehmer['fms_fahrer_nr'] ?? ''),
        'FMS-Code' => ($teilnehmer['fms_anmeldecode'] ?? '')
    ];

    foreach ($fields as $label => $value) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(50, 10, utf8_decode($label . ':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 10, utf8_decode($value), 0, 1, 'L');
        $pdf->Ln(1);
    }

    addWillkommenSeite($pdf, $teilnehmer);
}

// Dateiname z. B. mit Datum
if ($terminDatum) {
    $datePart = (new DateTime($terminDatum))->format('d-m-y');
    $filename = 'Schulung_Bestaetigte_Termin_' . $datePart . '.pdf';
} else {
    $filename = 'Schulung_Alle_Bestaetigten_' . date('d-m-y') . '.pdf';
}
$pdf->Output('D', $filename);
exit();