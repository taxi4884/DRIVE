<?php
require_once '../includes/bootstrap.php';
require_once '../includes/absencetypes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$abwesenheitId = isset($_POST['abwesenheit_id']) ? (int)$_POST['abwesenheit_id'] : 0;
$action = $_POST['action'] ?? 'update';

if ($abwesenheitId <= 0) {
    die('Ungültige Abwesenheits-ID.');
}

$stmt = $pdo->prepare('SELECT * FROM verwaltung_abwesenheit WHERE id = :id');
$stmt->execute(['id' => $abwesenheitId]);
$eintrag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eintrag) {
    die('Eintrag nicht gefunden.');
}

$rollen = array_map('trim', (array)($_SESSION['sekundarRolle'] ?? []));
$isAdmin = ($_SESSION['rolle'] ?? '') === 'Admin' || in_array('Admin', $rollen, true);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && (int)$eintrag['mitarbeiter_id'] !== $currentUserId) {
    http_response_code(403);
    die('Keine Berechtigung, diesen Eintrag zu bearbeiten.');
}

if ($action === 'delete') {
    $deleteStmt = $pdo->prepare('DELETE FROM verwaltung_abwesenheit WHERE id = :id');
    $deleteStmt->execute(['id' => $abwesenheitId]);

    header('Location: verwaltung_abwesenheit.php?deleted=1');
    exit;
}

$typ = $_POST['typ'] ?? '';
$beschreibung = $_POST['beschreibung'] ?? null;

$daten = [
    'id' => $abwesenheitId,
    'typ' => $typ,
    'beschreibung' => $beschreibung,
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
        die('Fehlendes Datum.');
    }
    $startObj = DateTime::createFromFormat('Y-m-d', $startdatum);
    $endObj = DateTime::createFromFormat('Y-m-d', $enddatum);
    if (!$startObj || $startObj->format('Y-m-d') !== $startdatum || !$endObj || $endObj->format('Y-m-d') !== $enddatum || $startdatum > $enddatum) {
        die('Ungültiger Zeitraum.');
    }
    $daten['startdatum'] = $startdatum;
    $daten['enddatum'] = $enddatum;
} elseif (in_array($typ, $ABSENCE_TYPES['time_point'], true)) {
    $tag = $_POST['tag'] ?? null;
    $zeit = $_POST['zeit'] ?? null;
    if (!$tag || !$zeit) {
        die('Fehlendes Datum oder Uhrzeit.');
    }
    $tagObj = DateTime::createFromFormat('Y-m-d', $tag);
    $zeitObj = DateTime::createFromFormat('H:i', $zeit);
    if (!$tagObj || $tagObj->format('Y-m-d') !== $tag || !$zeitObj || $zeitObj->format('H:i') !== $zeit) {
        die('Ungültiges Datum oder Uhrzeit.');
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
        die('Fehlende Zeitangaben.');
    }
    $tagObj = DateTime::createFromFormat('Y-m-d', $tag);
    $vonObj = DateTime::createFromFormat('H:i', $von);
    $bisObj = DateTime::createFromFormat('H:i', $bis);
    if (!$tagObj || $tagObj->format('Y-m-d') !== $tag || !$vonObj || $vonObj->format('H:i') !== $von || !$bisObj || $bisObj->format('H:i') !== $bis) {
        die('Ungültiges Datum oder Uhrzeit.');
    }
    $daten['datum'] = $tag;
    $daten['startzeit'] = $von;
    $daten['endzeit'] = $bis;
} else {
    die('Unbekannter Typ.');
}

$updateStmt = $pdo->prepare(
    'UPDATE verwaltung_abwesenheit
     SET datum = :datum,
         startdatum = :startdatum,
         enddatum = :enddatum,
         startzeit = :startzeit,
         endzeit = :endzeit,
         typ = :typ,
         beschreibung = :beschreibung
     WHERE id = :id'
);
$updateStmt->execute($daten);

header('Location: verwaltung_abwesenheit.php?updated=1');
exit;
