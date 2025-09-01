<?php
require_once '../includes/db.php'; // Verbindung zur Datenbank herstellen
require_once '../fpdf/fpdf.php'; // FPDF-Bibliothek einbinden

// Fehlerausgabe aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Funktion für UTF-8 zu ISO-8859-1 Konvertierung
function utf8_to_iso88591($text) {
    $converted = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    if ($converted === false) {
        return 'Fehler beim Konvertieren'; // Fallback-Text bei Konvertierungsfehler
    }
    return $converted;
}

if (!isset($_GET['id'])) {
    die("<p style='color: red;'>Fehler: Keine Teilnehmer-ID angegeben!</p>");
}

$id = (int)$_GET['id'];

// Teilnehmerdaten aus der Datenbank abrufen
$query = "
    SELECT 
        vorname, 
        nachname, 
        geburtsdatum, 
        strasse, 
        hausnummer, 
        postleitzahl, 
        ort, 
        handynummer, 
        email 
    FROM schulungsteilnehmer 
    WHERE id = :id
";
$stmt = $pdo->prepare($query);
$stmt->execute([':id' => $id]);
$teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teilnehmer) {
    die("<p style='color: red;'>Fehler: Teilnehmer mit der ID $id wurde nicht gefunden!</p>");
}

// PDF-Generierung starten
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true);
$pdf->SetFont('Arial', '', 12);

// Titel
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_to_iso88591('Gestattungsvertrag'), 0, 1, 'C');
$pdf->Ln(10);

// Vertragskopf
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 8, utf8_to_iso88591("
zwischen

4884 – Ihr Funktaxi
Älteste Leipziger Funktaxenzentrale GmbH
Lützner Straße 179
04179 Leipzig

- im Folgenden 4884 genannt - 
und

dem Chauffeur / der Chauffeurin

Vorname, Name: {$teilnehmer['vorname']} {$teilnehmer['nachname']}
Geburtsdatum: {$teilnehmer['geburtsdatum']}
Straße, HsNr: {$teilnehmer['strasse']} {$teilnehmer['hausnummer']}
PLZ, Wohnort: {$teilnehmer['postleitzahl']} {$teilnehmer['ort']}
Handy: {$teilnehmer['handynummer']}
E-Mail: {$teilnehmer['email']}

- im Folgenden Chauffeur genannt -
"));
$pdf->Ln(10);

// Präambel
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_to_iso88591('Präambel'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 8, utf8_to_iso88591("
4884 ist ein Unternehmen, dass sich auf die Vermittlung von Fahraufträgen spezialisiert hat und steht seinen Vertragspartnern auf höchstem technischen und personellen Niveau zur Verfügung.
4884 hat es sich zum Ziel gesetzt, im Zusammenwirken mit den angeschlossenen Teilnehmern und ihren angestellten Chauffeuren (Unternehmensverbund 4884), den Fahrgästen in besonderem Maße dienlich zu sein, um so das Fahrtenaufkommen zu erhöhen.
Um dies zu erreichen, ist eine deutliche Herausstellung der Dienstleistungen der Vertragsparteien erforderlich. Nähere Angaben zu den damit verbundenen Pflichten, insbesondere zu Kleidung und Aussehen der Fahrzeuge, dem Verhalten gegenüber den Fahrgästen, den Bestimmungen zur Funktechnik etc., enthält die Fahr- und Funkdienstordnung. Die Fahr- und Funkdienstordnung in ihrer jeweils gültigen Fassung wird ausdrücklich Bestandteil dieses Vertrages.
4884 hat mit jedem Teilnehmer einen Vertrag zur Erreichung der o.g. Ziele geschlossen.

Dies vorausgeschickt, vereinbaren die Parteien Folgendes:
"));
$pdf->Ln(5);

// Paragraphen: § 1 - § 8
$paragraphs = [
    '§ 1' => "
1) Mit Abschluss dieses Vertrages und nach erfolgreicher Teilnahme am ersten Dienstleistungstraining und bestandener Prüfung erwirbt der Chauffeur das Recht, an der Fahrtenvermittlung von 4884 teilzunehmen. ...
2) Jeder Chauffeur erhält von 4884 einen nicht übertragbaren Funkteilnehmerausweis mit Lichtbild ...
3) Der Funkteilnehmerausweis verliert seine Gültigkeit mit Beendigung des Teilnehmer- oder dieses Gestattungsvertrages. ...
",
    '§ 2' => "
1) Der Gestattungsvertrag beginnt mit Abschluss des Vertrages und wird für die Dauer eines Jahres geschlossen. ...
2) Er verlängert sich jeweils um ein weiteres Jahr, wenn er nicht mit einer Frist von drei Monaten zu seinem vorgenannten Ablauf gekündigt wird. ...
",
    '§ 3' => "Die Abtretung der sich für den Chauffeur aus diesem Vertrag ergebenden Rechte ist nicht zulässig, es sei denn, 4884 stimmt der Abtretung schriftlich zu.",
    '§ 4' => "Die jeweils gültige Fassung der Fahr- und Funkdienstordnung wird Bestandteil dieses Vertrages.",
];

// Paragraphen ausgeben
foreach ($paragraphs as $title => $text) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_to_iso88591($title), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 8, utf8_to_iso88591($text));
    $pdf->Ln(5);
}

// Unterschriftenbereich
$pdf->Ln(10);
$pdf->MultiCell(0, 8, utf8_to_iso88591("
Leipzig, den 


___________________________            ___________________________
Chauffeur                               4884
"));

// PDF im Browser anzeigen
$pdf->Output('I', "Gestattungsvertrag_{$teilnehmer['nachname']}.pdf");
