<?php
require_once '../includes/db.php';

if (!isset($_GET['id'])) {
    die("Keine ID angegeben.");
}

$id = (int)$_GET['id'];

// Teilnehmerdaten laden
$stmt = $pdo->prepare("SELECT vorname, schulungstermin FROM schulungsteilnehmer WHERE id = :id");
$stmt->execute([':id' => $id]);
$teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teilnehmer) {
    die("Teilnehmer nicht gefunden.");
}

$vorname = $teilnehmer['vorname'];
$datum = DateTime::createFromFormat('Y-m-d', $teilnehmer['schulungstermin']);

if (!$datum) {
    die("Ungültiges Datum.");
}

$dtStart = clone $datum;
$dtStart->setTime(9, 0);
$dtEnd = (clone $dtStart)->modify('+6 hours');

$uid = uniqid() . "@4884.de";
$dtStamp = (new DateTime())->format('Ymd\THis\Z');
$dtStartS = $dtStart->format('Ymd\THis');
$dtEndS = $dtEnd->format('Ymd\THis');

$ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Taxi4884//Funkschulung//DE
METHOD:REQUEST
BEGIN:VEVENT
UID:$uid
DTSTAMP:$dtStamp
DTSTART:$dtStartS
DTEND:$dtEndS
SUMMARY:Praxistag Funkschulung
LOCATION:Lützner Straße 179, 04179 Leipzig
DESCRIPTION:Praxistag zur Funkschulung – bitte um 09:00 Uhr im Büro melden
END:VEVENT
END:VCALENDAR
ICS;

// Header für Dateidownload
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="praxistag.ics"');

echo $ics;
exit;
?>
