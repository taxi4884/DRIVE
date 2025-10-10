<?php
require_once '../includes/bootstrap.php'; // deine PDO-Verbindung
require_once '../includes/absencetypes.php';
require_once '../includes/mailer.php';

/**
 * Prüft, ob ein Benutzer zur Verwaltung gehört.
 */
function userHasVerwaltungRole(?string $primaryRole, ?string $secondaryRoles): bool
{
    $normalizedPrimary = strtolower(trim((string) $primaryRole));
    if ($normalizedPrimary === 'verwaltung') {
        return true;
    }

    $roles = array_filter(array_map(
        static function ($role) {
            return strtolower(trim((string) $role));
        },
        explode(',', (string) $secondaryRoles)
    ));

    return in_array('verwaltung', $roles, true);
}

/**
 * Ermittelt alle Empfänger, die Abwesenheiten genehmigen dürfen.
 *
 * @return array<int, array{email: string, name: string}>
 */
function fetchVerwaltungRecipients(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT Name, Email, Rolle, SekundarRolle FROM Benutzer WHERE Email IS NOT NULL AND Email <> ''");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $recipients = [];

    foreach ($rows as $row) {
        if (!userHasVerwaltungRole($row['Rolle'] ?? null, $row['SekundarRolle'] ?? null)) {
            continue;
        }

        $email = trim((string) ($row['Email'] ?? ''));
        if ($email === '') {
            continue;
        }

        $recipients[$email] = [
            'email' => $email,
            'name' => $row['Name'] ?? $email,
        ];
    }

    return array_values($recipients);
}

function formatGermanDate(?string $date): ?string
{
    if (empty($date)) {
        return null;
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d.m.Y', $timestamp) : null;
}

function formatGermanTime(?string $time): ?string
{
    if (empty($time)) {
        return null;
    }

    $timestamp = strtotime($time);
    return $timestamp ? date('H:i', $timestamp) : null;
}

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

// Name des Mitarbeiters für Benachrichtigungen ermitteln
$mitarbeiterName = null;
try {
    $mitarbeiterStmt = $pdo->prepare('SELECT Name FROM Benutzer WHERE BenutzerID = :id LIMIT 1');
    $mitarbeiterStmt->execute([':id' => $mitarbeiter_id]);
    $mitarbeiter = $mitarbeiterStmt->fetch(PDO::FETCH_ASSOC);
    if ($mitarbeiter && isset($mitarbeiter['Name'])) {
        $mitarbeiterName = $mitarbeiter['Name'];
    }
} catch (PDOException $e) {
    // Ignorieren – Name ist optional für die Benachrichtigung
}

// In Datenbank speichern
$stmt = $pdo->prepare(
    "INSERT INTO verwaltung_abwesenheit (mitarbeiter_id, datum, startdatum, enddatum, startzeit, endzeit, typ, beschreibung, erstellt_von)
     VALUES (:mitarbeiter_id, :datum, :startdatum, :enddatum, :startzeit, :endzeit, :typ, :beschreibung, :erstellt_von)"
);
$stmt->execute($daten);

// Benachrichtigung für genehmigungspflichtige Abwesenheiten versenden
if (!in_array($typ, ['Krank', 'Kind Krank'], true)) {
    $recipients = fetchVerwaltungRecipients($pdo);

    if (!empty($recipients)) {
        $absencelabel = $ABSENCE_TYPE_LABELS[$typ] ?? $typ;

        $details = [
            'Mitarbeiter' => $mitarbeiterName ?: ('ID ' . $mitarbeiter_id),
            'Abwesenheitstyp' => $absencelabel,
        ];

        $periodStart = formatGermanDate($daten['startdatum']);
        $periodEnd = formatGermanDate($daten['enddatum']);
        if ($periodStart || $periodEnd) {
            $details['Zeitraum'] = trim(($periodStart ?? '?') . ' – ' . ($periodEnd ?? '?'));
        }

        $singleDay = formatGermanDate($daten['datum']);
        if ($singleDay) {
            $details['Datum'] = $singleDay;
        }

        $fromTime = formatGermanTime($daten['startzeit']);
        $toTime = formatGermanTime($daten['endzeit']);
        if ($fromTime || $toTime) {
            $details['Uhrzeit'] = trim(($fromTime ?? '') . ($toTime ? ' – ' . $toTime : ''));
        }

        if (!empty($beschreibung)) {
            $details['Beschreibung'] = $beschreibung;
        }

        if (!empty($_SESSION['user_name'])) {
            $details['Erfasst von'] = $_SESSION['user_name'];
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $overviewLink = $host ? sprintf('%s://%s/verwaltung_abwesenheit.php', $scheme, $host) : 'verwaltung_abwesenheit.php';

        $body = '<p>Es wurde eine neue Abwesenheit eingetragen, die eine Genehmigung erfordert:</p><ul>';
        foreach ($details as $label => $value) {
            $body .= sprintf('<li><strong>%s:</strong> %s</li>', htmlspecialchars((string) $label), nl2br(htmlspecialchars((string) $value)));
        }
        $body .= sprintf('</ul><p><a href="%s">Zur Übersicht der Abwesenheiten</a></p>', htmlspecialchars($overviewLink));

        $subject = sprintf('Neue Abwesenheit zur Genehmigung: %s', $mitarbeiterName ?: ('ID ' . $mitarbeiter_id));

        foreach ($recipients as $recipient) {
            sendEmail($recipient['email'], $recipient['name'], $subject, $body);
        }
    }
}

header("Location: verwaltung_abwesenheit.php?success=1");
exit;
