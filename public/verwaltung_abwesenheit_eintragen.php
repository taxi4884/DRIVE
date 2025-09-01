<?php
require_once '../includes/head.php'; // deine PDO-Verbindung

// Fehleranzeige für Debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Daten aus dem Formular
$mitarbeiter_id = $_POST['mitarbeiter_id'];
$typ = $_POST['typ'];
$beschreibung = $_POST['beschreibung'] ?? null;
$erstellt_von = $_SESSION['user_id']; // Aktuell eingeloggter Benutzer

// Initialisierung
$eintraege = [];

if (in_array($typ, ['Urlaub', 'Krank', 'Kind Krank'])) {
    $von = $_POST['von_datum'];
    $bis = $_POST['bis_datum'];

    if (!$von || !$bis) {
        die("Fehlendes Datum.");
    }

    $start = new DateTime($von);
    $end = new DateTime($bis);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

    foreach ($period as $day) {
        $eintraege[] = [
            'datum' => $day->format('Y-m-d'),
            'typ' => $typ,
            'von' => null,
            'bis' => null
        ];
    }
} elseif (in_array($typ, ['Kommt später', 'Geht eher'])) {
    $datum = $_POST['von_datum'] ?? $_POST['tag_zeitspanne'];
    $zeit = $_POST['zeitpunkt'] ?? null;

    if (!$datum || !$zeit) {
        die("Fehlendes Datum oder Uhrzeit.");
    }

    $eintraege[] = [
        'datum' => $datum,
        'typ' => $typ,
        'von' => ($typ === 'Kommt später') ? $zeit : null,
        'bis' => ($typ === 'Geht eher') ? $zeit : null
    ];
} elseif ($typ === 'Unterbrechung') {
    $datum = $_POST['tag_zeitspanne'];
    $von = $_POST['von_uhrzeit'];
    $bis = $_POST['bis_uhrzeit'];

    if (!$datum || !$von || !$bis) {
        die("Fehlende Zeitangaben.");
    }

    $eintraege[] = [
        'datum' => $datum,
        'typ' => $typ,
        'von' => $von,
        'bis' => $bis
    ];
} else {
    die("Unbekannter Typ.");
}

// In Datenbank speichern
$stmt = $pdo->prepare("
    INSERT INTO verwaltung_abwesenheit 
    (mitarbeiter_id, datum, typ, von, bis, beschreibung, erstellt_von)
    VALUES (:mitarbeiter_id, :datum, :typ, :von, :bis, :beschreibung, :erstellt_von)
");

foreach ($eintraege as $entry) {
    $stmt->execute([
        'mitarbeiter_id' => $mitarbeiter_id,
        'datum' => $entry['datum'],
        'typ' => $entry['typ'],
        'von' => $entry['von'],
        'bis' => $entry['bis'],
        'beschreibung' => $beschreibung,
        'erstellt_von' => $erstellt_von
    ]);
}

header("Location: verwaltung_abwesenheit.php?success=1");
exit;
