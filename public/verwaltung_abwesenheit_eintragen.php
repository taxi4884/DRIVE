<?php
require_once '../includes/bootstrap.php'; // deine PDO-Verbindung
require_once '../includes/absencetypes.php';

// Fehleranzeige für Debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Daten aus dem Formular
$mitarbeiter_id = $_POST['mitarbeiter_id'];
$typ = $_POST['typ'];
$beschreibung = $_POST['beschreibung'] ?? null;
$erstellt_von = $_SESSION['user_id']; // Aktuell eingeloggter Benutzer

// Initialisierung der Daten für die Speicherung
$daten = [
    'mitarbeiter_id' => $mitarbeiter_id,
    'typ' => $typ,
    'beschreibung' => $beschreibung,
    'erstellt_von' => $erstellt_von,
    'datum' => null,
    'startdatum' => null,
    'enddatum' => null,
    'startzeit' => null,
    'endzeit' => null,
];

if (in_array($typ, $ABSENCE_TYPES['period'], true)) {
    $startdatum = $_POST['startdatum'] ?? null;
    $enddatum = $_POST['enddatum'] ?? null;
    if (!$startdatum || !$enddatum) {
        die("Fehlendes Datum.");
    }
    $startObj = DateTime::createFromFormat('Y-m-d', $startdatum);
    $endObj = DateTime::createFromFormat('Y-m-d', $enddatum);
    if (!$startObj || $startObj->format('Y-m-d') !== $startdatum || !$endObj || $endObj->format('Y-m-d') !== $enddatum || $startdatum > $enddatum) {
        die("Ungültiger Zeitraum.");
    }
    $daten['startdatum'] = $startdatum;
    $daten['enddatum'] = $enddatum;
} elseif (in_array($typ, $ABSENCE_TYPES['time_point'], true)) {
    $tag = $_POST['tag'] ?? null;
    $zeit = $_POST['zeit'] ?? null;
    if (!$tag || !$zeit) {
        die("Fehlendes Datum oder Uhrzeit.");
    }
    $tagObj = DateTime::createFromFormat('Y-m-d', $tag);
    $zeitObj = DateTime::createFromFormat('H:i', $zeit);
    if (!$tagObj || $tagObj->format('Y-m-d') !== $tag || !$zeitObj || $zeitObj->format('H:i') !== $zeit) {
        die("Ungültiges Datum oder Uhrzeit.");
    }
    $daten['datum'] = $tag;
    if ($typ === 'Kommt später') {
        $daten['startzeit'] = $zeit;
    } else {
        $daten['endzeit'] = $zeit;
    }
} elseif (in_array($typ, $ABSENCE_TYPES['time_range'], true)) {
    $tag = $_POST['tag'] ?? null;
    $von = $_POST['von_uhrzeit'] ?? null;
    $bis = $_POST['bis_uhrzeit'] ?? null;
    if (!$tag || !$von || !$bis) {
        die("Fehlende Zeitangaben.");
    }
    $tagObj = DateTime::createFromFormat('Y-m-d', $tag);
    $vonObj = DateTime::createFromFormat('H:i', $von);
    $bisObj = DateTime::createFromFormat('H:i', $bis);
    if (!$tagObj || $tagObj->format('Y-m-d') !== $tag || !$vonObj || $vonObj->format('H:i') !== $von || !$bisObj || $bisObj->format('H:i') !== $bis) {
        die("Ungültiges Datum oder Uhrzeit.");
    }
    $daten['datum'] = $tag;
    $daten['startzeit'] = $von;
    $daten['endzeit'] = $bis;
} else {
    die("Unbekannter Typ.");
}

// In Datenbank speichern
$stmt = $pdo->prepare(
    "INSERT INTO verwaltung_abwesenheit (mitarbeiter_id, datum, startdatum, enddatum, startzeit, endzeit, typ, beschreibung, erstellt_von)
     VALUES (:mitarbeiter_id, :datum, :startdatum, :enddatum, :startzeit, :endzeit, :typ, :beschreibung, :erstellt_von)"
);
$stmt->execute($daten);

header("Location: verwaltung_abwesenheit.php?success=1");
exit;
