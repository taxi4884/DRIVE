<?php
// Include authentication and database connection
require_once '../includes/bootstrap.php';

if (!function_exists('tableHasColumn')) {
    /**
     * Prüft, ob eine bestimmte Spalte in einer Tabelle existiert.
     */
    function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
        $stmt->execute(['column' => $column]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}

// Get user information for greeting
$user_name = $_SESSION['user_name'] ?? 'Gast';

// Dynamische Begrüßung erstellen
$greetings = [
    "Willkommen zurück, ",
    "Schön, dich zu sehen, ",
    "Hallo und herzlich willkommen, ",
    "Einen guten Tag, ",
    "Freut mich, dich zu sehen, ",
    "Schön, dass du wieder hier bist, ",
    "Willkommen auf deinem Dashboard, ",
    "Hallo, ",
    "Wie schön, dich wiederzusehen, ",
    "Einen erfolgreichen Tag wünsche ich dir, ",
    "Guten Tag, ",
    "Auf ein erfolgreiches Arbeiten, ",
    "Schön, dass du vorbeischaust, ",
    "Lass uns loslegen, ",
    "Einen produktiven Tag, ",
    "Bereit für die Aufgaben des Tages, ",
    "Hi, ",
    "Toll, dass du da bist, ",
    "Heute rocken wir es, ",
    "Was für ein schöner Tag, "
];

$greeting_message = $greetings[array_rand($greetings)] . htmlspecialchars($user_name) . "!";

// Dynamische Dashboard-Titel erstellen
$dashboardTitles = [
    "Dein personalisiertes Dashboard für den heutigen Tag",
    "Dein Tages-Dashboard – alle Infos auf einen Blick",
    "Dein Überblick für heute",
    "Dein individueller 24-Stunden-Überblick",
    "Alles Wichtige für deinen Tag",
    "Dein kompakter Tages-Report",
    "Dein persönliches Tagesbriefing",
    "Dein maßgeschneiderter Tagesüberblick",
    "Dein Start in den Tag mit allen Highlights",
    "Dein Dashboard, dein Vorteil"
];

$subtitle_message = $dashboardTitles[array_rand($dashboardTitles)];

// Zeitraum für Dienstplan: Morgen + 5 Tage
$start_date = date('Y-m-d', strtotime('+1 day'));
$end_date   = date('Y-m-d', strtotime('+6 days'));

// Mitarbeiter abrufen
$stmt = $pdo->prepare("SELECT vorname, nachname, mitarbeiter_id FROM mitarbeiter_zentrale WHERE status = 'Aktiv' ORDER BY nachname ASC");
$stmt->execute();
$mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Wochentage für den Zeitraum vorbereiten
$dates = [];
for ($date = strtotime($start_date); $date <= strtotime($end_date); $date = strtotime('+1 day', $date)) {
    $dates[] = [
        'day'       => date('d.m.', $date),
        'isWeekend' => in_array(date('N', $date), [6, 7]), // Samstag oder Sonntag
        'date'      => date('Y-m-d', $date),
    ];
}

// Schichten aus dem Dienstplan abrufen
$stmt = $pdo->prepare(
    "SELECT dp.mitarbeiter_id, dp.datum, s.name AS schicht_name
     FROM dienstplan dp
     LEFT JOIN schichten s ON dp.schicht_id = s.schicht_id
     WHERE dp.datum BETWEEN :start_date AND :end_date"
);
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$dienstplan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dienstplan als assoziatives Array strukturieren
$dienstplanMap = [];
foreach ($dienstplan as $entry) {
    $dienstplanMap[$entry['mitarbeiter_id']][$entry['datum']] = $entry['schicht_name'];
}

// Datumstext für den Header
$daysOfWeek = [
    'Monday'    => 'Montag',
    'Tuesday'   => 'Dienstag',
    'Wednesday' => 'Mittwoch',
    'Thursday'  => 'Donnerstag',
    'Friday'    => 'Freitag',
    'Saturday'  => 'Samstag',
    'Sunday'    => 'Sonntag',
];
$todayLabel = ($daysOfWeek[date('l')] ?? date('l')) . ', ' . date('d.m.y');

// Aktive Fahrer strukturieren
$activeDriversByCompany = [];
$activeDriversError     = null;
try {
    $stmt = $pdo->prepare(
        "SELECT
            sfa.unternehmer AS firmenname,
            COALESCE(f.Vorname, 'Unbekannt') AS fahrer_vorname,
            COALESCE(f.Nachname, '') AS fahrer_nachname,
            COALESCE(v.Kennzeichen, 'Ersatzfahrzeug') AS kennzeichen,
            sfa.anmeldung,
            sfa.fahrzeugflotte
        FROM sync_fahreranmeldung sfa
        LEFT JOIN Fahrer f
            ON sfa.fahrer = f.fms_alias OR sfa.fahrer = f.Fahrernummer
        LEFT JOIN Fahrzeuge v
            ON sfa.kennung = v.Konzessionsnummer OR sfa.kennung = v.fms_alias
        WHERE sfa.abmeldung IS NULL
        ORDER BY firmenname, sfa.anmeldung DESC"
    );
    $stmt->execute();
    $aktive = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($aktive as $eintrag) {
        switch ($eintrag['firmenname']) {
            case '720':
                $anzeigeName = '4884 – Ihr Funktaxi';
                break;
            case '5810':
                $anzeigeName = '4884 Service GbR';
                break;
            case '292':
                $anzeigeName = 'Cityline GbR';
                break;
            default:
                $anzeigeName = 'Unbekannter Unternehmer (' . $eintrag['firmenname'] . ')';
        }
        $activeDriversByCompany[$anzeigeName][] = $eintrag;
    }
} catch (PDOException $e) {
    $activeDriversError = $e->getMessage();
}

$totalActiveDrivers = array_sum(array_map('count', $activeDriversByCompany));

// Geburtstage
$fahrer_birthdays = [];
$zentrale_birthdays = [];
$birthdaysError = null;
$hasZentraleBirthdateColumn = tableHasColumn($pdo, 'mitarbeiter_zentrale', 'geburtsdatum');
try {
    $stmt_fahrer = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS name FROM Fahrer WHERE DATE_FORMAT(geburtsdatum, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')");
    $stmt_fahrer->execute();
    $fahrer_birthdays = $stmt_fahrer->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $birthdaysError = $e->getMessage();
}

if ($hasZentraleBirthdateColumn) {
    try {
        $stmt_zentrale = $pdo->prepare(
            "SELECT CONCAT(Vorname, ' ', Nachname) AS name
             FROM mitarbeiter_zentrale
             WHERE DATE_FORMAT(geburtsdatum, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')
               AND status = 'Aktiv'"
        );
        $stmt_zentrale->execute();
        $zentrale_birthdays = $stmt_zentrale->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $birthdaysError = $birthdaysError ? $birthdaysError . ' / ' . $e->getMessage() : $e->getMessage();
    }
} else {
    $birthdaysError = $birthdaysError
        ? $birthdaysError . ' / Spalte "geburtsdatum" existiert nicht in mitarbeiter_zentrale.'
        : 'Spalte "geburtsdatum" existiert nicht in mitarbeiter_zentrale.';
}

$birthdays      = array_merge($fahrer_birthdays, $zentrale_birthdays);
$birthdaysCount = count($birthdays);

// Wartungen heute
$maintenancesDueToday      = [];
$maintenancesDueTodayError = null;
try {
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(w.Wartungsdatum, '%d.%m.%Y') AS Wartungsdatum, f.Konzessionsnummer, f.Kennzeichen, f.Marke, f.Modell
         FROM Wartung w
         JOIN Fahrzeuge f ON w.FahrzeugID = f.FahrzeugID
         WHERE DATE(w.Wartungsdatum) = CURDATE()"
    );
    $stmt->execute();
    $maintenancesDueToday = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $maintenancesDueTodayError = $e->getMessage();
}
$maintenancesDueTodayCount = count($maintenancesDueToday);

// Wartungstermine in den nächsten drei Monaten
$upcomingMaintenances      = [];
$upcomingMaintenancesError = null;
try {
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(w.Wartungsdatum, '%d.%m.%Y') AS FormattedDate, w.Werkstatt, f.Konzessionsnummer, f.Marke, f.Modell
         FROM Wartung w
         JOIN Fahrzeuge f ON w.FahrzeugID = f.FahrzeugID
         WHERE w.Wartungsdatum BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH)
         ORDER BY w.Wartungsdatum ASC"
    );
    $stmt->execute();
    $upcomingMaintenances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $upcomingMaintenancesError = $e->getMessage();
}

// Fällige TÜV
$huDue    = [];
$huDueError = null;
try {
    $stmt = $pdo->prepare("SELECT Konzessionsnummer, Marke, Modell, DATE_FORMAT(HU, '%d.%m.%Y') AS HU FROM Fahrzeuge WHERE HU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) ORDER BY HU ASC");
    $stmt->execute();
    $huDue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $huDueError = $e->getMessage();
}

// P-Schein Gültigkeit
$pscheinDue  = [];
$pscheinError = null;
try {
    $stmt = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS Name, DATE_FORMAT(PScheinGueltigkeit, '%d.%m.%Y') AS PScheinGueltigkeit FROM Fahrer WHERE PScheinGueltigkeit BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) ORDER BY PScheinGueltigkeit ASC");
    $stmt->execute();
    $pscheinDue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pscheinError = $e->getMessage();
}

// Abwesenheiten
$fahrerAbsences      = [];
$fahrerAbsencesError = null;
try {
    $stmt_fahrer_absences = $pdo->prepare(
        "SELECT CONCAT(f.Vorname, ' ', f.Nachname) AS name, a.abwesenheitsart, DATE_FORMAT(a.startdatum, '%d.%m.%Y') AS startdatum, DATE_FORMAT(a.enddatum, '%d.%m.%Y') AS enddatum
         FROM FahrerAbwesenheiten a
         JOIN Fahrer f ON a.FahrerID = f.FahrerID
         WHERE CURDATE() BETWEEN a.startdatum AND a.enddatum"
    );
    $stmt_fahrer_absences->execute();
    $fahrerAbsences = $stmt_fahrer_absences->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fahrerAbsencesError = $e->getMessage();
}

$zentraleAbsences      = [];
$zentraleAbsencesError = null;
try {
    $stmt_zentrale_absences = $pdo->prepare(
        "SELECT az.mitarbeiter_id,
                CONCAT(mz.Vorname, ' ', mz.Nachname) AS name,
                az.typ,
                az.startdatum,
                az.enddatum
         FROM abwesenheiten_zentrale az
         JOIN mitarbeiter_zentrale mz ON az.mitarbeiter_id = mz.mitarbeiter_id
         WHERE CURDATE() BETWEEN az.startdatum AND az.enddatum
           AND mz.status = 'Aktiv'
         ORDER BY az.mitarbeiter_id, az.typ, az.startdatum"
    );
    $stmt_zentrale_absences->execute();
    $currentZentraleAbsences = $stmt_zentrale_absences->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($currentZentraleAbsences)) {
        $datesStmt = $pdo->prepare(
            "SELECT startdatum, enddatum
             FROM abwesenheiten_zentrale
             WHERE mitarbeiter_id = :mitarbeiter_id
               AND typ = :typ
             ORDER BY startdatum"
        );

        $todayTimestamp = strtotime('today');
        $processedKeys  = [];

        foreach ($currentZentraleAbsences as $absence) {
            $key = $absence['mitarbeiter_id'] . '|' . $absence['typ'];

            if (isset($processedKeys[$key])) {
                continue;
            }

            $datesStmt->execute([
                'mitarbeiter_id' => $absence['mitarbeiter_id'],
                'typ'            => $absence['typ'],
            ]);

            $entries = $datesStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($entries)) {
                $processedKeys[$key] = true;
                continue;
            }

            $groups     = [];
            $groupStart = null;
            $groupEnd   = null;

            foreach ($entries as $entry) {
                $entryStart = strtotime($entry['startdatum']);
                $entryEnd   = strtotime($entry['enddatum']);

                if ($entryStart === false || $entryEnd === false) {
                    continue;
                }

                if ($groupStart === null) {
                    $groupStart = $entryStart;
                    $groupEnd   = $entryEnd;
                    continue;
                }

                $expectedNext = strtotime('+1 day', $groupEnd);

                if ($entryStart <= $expectedNext) {
                    if ($entryEnd > $groupEnd) {
                        $groupEnd = $entryEnd;
                    }
                } else {
                    $groups[] = [
                        'start' => $groupStart,
                        'end'   => $groupEnd,
                    ];

                    $groupStart = $entryStart;
                    $groupEnd   = $entryEnd;
                }
            }

            if ($groupStart !== null) {
                $groups[] = [
                    'start' => $groupStart,
                    'end'   => $groupEnd,
                ];
            }

            foreach ($groups as $group) {
                if ($todayTimestamp >= $group['start'] && $todayTimestamp <= $group['end']) {
                    $zentraleAbsences[] = [
                        'name'       => $absence['name'],
                        'typ'        => $absence['typ'],
                        'startdatum' => date('d.m.Y', $group['start']),
                        'enddatum'   => date('d.m.Y', $group['end']),
                    ];
                    break;
                }
            }

            $processedKeys[$key] = true;
        }
    }
} catch (PDOException $e) {
    $zentraleAbsencesError = $e->getMessage();
}

$totalAbsences = count($fahrerAbsences) + count($zentraleAbsences);

// Heutige Schicht in der Zentrale
$heutige_schicht     = [];
$heutigeSchichtError = null;
try {
    $stmt_today = $pdo->prepare(
        "SELECT CONCAT(mz.vorname, ' ', mz.nachname) AS name, s.name AS schicht
         FROM dienstplan d
         JOIN mitarbeiter_zentrale mz ON d.mitarbeiter_id = mz.mitarbeiter_id
         JOIN schichten s ON d.schicht_id = s.schicht_id
         WHERE DATE(d.datum) = CURDATE()
           AND mz.status = 'Aktiv'"
    );
    $stmt_today->execute();
    $heutige_schicht = $stmt_today->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $heutigeSchichtError = $e->getMessage();
}

$title = 'Dashboard';
include '../includes/layout.php';
?>
    <div class="container py-4 dashboard-container">
        <header class="dashboard-hero card shadow-sm">
            <div class="card-body">
                <div class="hero-content">
                    <p class="hero-eyebrow">Zentrale Übersicht</p>
                    <h1 id="greeting" class="hero-title"></h1>
                    <p class="hero-subtitle" id="subtitle"><?php echo $subtitle_message; ?></p>
                </div>
                <div class="hero-meta">
                    <span class="hero-date"><i class="bi bi-calendar-event"></i> <?php echo htmlspecialchars($todayLabel); ?></span>
                </div>
            </div>
        </header>

        <section class="dashboard-quickstats">
            <article class="stat-card">
                <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Aktive Fahrer</span>
                    <span class="stat-value"><?php echo htmlspecialchars($totalActiveDrivers); ?></span>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cake2-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Geburtstage heute</span>
                    <span class="stat-value"><?php echo htmlspecialchars($birthdaysCount); ?></span>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-emoji-frown-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Abwesenheiten</span>
                    <span class="stat-value"><?php echo htmlspecialchars($totalAbsences); ?></span>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-tools"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Wartungen heute</span>
                    <span class="stat-value"><?php echo htmlspecialchars($maintenancesDueTodayCount); ?></span>
                </div>
            </article>
        </section>

        <main class="dashboard-main">
            <section id="aktive-fahrer" class="widget card grid-span-2">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-speedometer2"></i> Aktive Fahrer</h2>
                        <p class="section-subtitle">Live-Übersicht der aktuell angemeldeten Fahrer</p>
                    </div>
                    <?php if ($activeDriversError): ?>
                        <p class="text-danger mb-0">Fehler bei der Fahrerabfrage: <?php echo htmlspecialchars($activeDriversError); ?></p>
                    <?php elseif (!empty($activeDriversByCompany)): ?>
                        <?php foreach ($activeDriversByCompany as $firma => $fahrerListe): ?>
                            <div class="company-card">
                                <div class="company-card-header">
                                    <h3 class="company-title"><?php echo htmlspecialchars($firma); ?></h3>
                                    <span class="badge text-bg-primary"><?php echo count($fahrerListe); ?> Fahrer</span>
                                </div>
                                <ul class="stacked-list">
                                    <?php foreach ($fahrerListe as $fahrer): ?>
                                        <li class="stacked-list-item">
                                            <div class="list-primary"><?php echo htmlspecialchars(trim($fahrer['fahrer_vorname'] . ' ' . $fahrer['fahrer_nachname'])); ?></div>
                                            <div class="list-secondary">
                                                <span><i class="bi bi-car-front"></i> <?php echo htmlspecialchars($fahrer['kennzeichen']); ?></span>
                                            </div>
                                            <?php if (!empty($fahrer['anmeldung'])): ?>
                                                <div class="list-meta"><i class="bi bi-clock-history"></i> seit <?php echo htmlspecialchars(date('d.m.y H:i', strtotime($fahrer['anmeldung']))); ?></div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Aktuell sind keine Fahrer angemeldet.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="tageshighlights" class="widget card">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-stars"></i> Tageshighlights</h2>
                        <p class="section-subtitle">Was heute wichtig ist</p>
                    </div>
                    <div class="two-column">
                        <div class="info-block">
                            <h3 class="info-title"><i class="bi bi-cake2"></i> Geburtstage</h3>
                            <?php if ($birthdaysError): ?>
                                <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($birthdaysError); ?></p>
                            <?php elseif (!empty($birthdays)): ?>
                                <ul class="stacked-list">
                                    <?php foreach ($birthdays as $person): ?>
                                        <li class="stacked-list-item">
                                            <div class="list-primary"><?php echo htmlspecialchars($person['name']); ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Keine Geburtstage heute.</p>
                            <?php endif; ?>
                        </div>
                        <div class="info-block">
                            <h3 class="info-title"><i class="bi bi-tools"></i> Fällige Wartungen</h3>
                            <?php if ($maintenancesDueTodayError): ?>
                                <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($maintenancesDueTodayError); ?></p>
                            <?php elseif (!empty($maintenancesDueToday)): ?>
                                <ul class="stacked-list">
                                    <?php foreach ($maintenancesDueToday as $wartung): ?>
                                        <li class="stacked-list-item">
                                            <div class="list-primary"><?php echo htmlspecialchars($wartung['Marke'] . ' ' . $wartung['Modell']); ?></div>
                                            <div class="list-secondary">
                                                <span><i class="bi bi-calendar-date"></i> <?php echo htmlspecialchars($wartung['Wartungsdatum']); ?></span>
                                                <span><i class="bi bi-123"></i> <?php echo htmlspecialchars($wartung['Kennzeichen']); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Keine fälligen Wartungen heute.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section id="krank-urlaub" class="widget card grid-span-2">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-heart-pulse"></i> Krank &amp; Urlaub</h2>
                        <p class="section-subtitle">Aktuelle Abwesenheiten in Fahrerteam und Zentrale</p>
                    </div>
                    <div class="two-column">
                        <div class="info-block">
                            <h3 class="info-title"><i class="bi bi-steering-wheel"></i> Fahrer</h3>
                            <?php if ($fahrerAbsencesError): ?>
                                <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($fahrerAbsencesError); ?></p>
                            <?php elseif (!empty($fahrerAbsences)): ?>
                                <ul class="stacked-list">
                                    <?php foreach ($fahrerAbsences as $absence): ?>
                                        <li class="stacked-list-item">
                                            <div class="list-primary"><?php echo htmlspecialchars($absence['name']); ?></div>
                                            <div class="list-secondary">
                                                <span class="badge text-bg-warning"><?php echo htmlspecialchars($absence['abwesenheitsart']); ?></span>
                                                <span><i class="bi bi-calendar-date"></i> <?php echo htmlspecialchars($absence['startdatum']); ?> – <?php echo htmlspecialchars($absence['enddatum']); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Keine Abwesenheiten</p>
                            <?php endif; ?>
                        </div>
                        <div class="info-block">
                            <h3 class="info-title"><i class="bi bi-briefcase"></i> Zentrale</h3>
                            <?php if ($zentraleAbsencesError): ?>
                                <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($zentraleAbsencesError); ?></p>
                            <?php elseif (!empty($zentraleAbsences)): ?>
                                <ul class="stacked-list">
                                    <?php foreach ($zentraleAbsences as $absence): ?>
                                        <li class="stacked-list-item">
                                            <div class="list-primary"><?php echo htmlspecialchars($absence['name']); ?></div>
                                            <div class="list-secondary">
                                                <span class="badge text-bg-warning"><?php echo htmlspecialchars($absence['typ']); ?></span>
                                                <span><i class="bi bi-calendar-date"></i> <?php echo htmlspecialchars($absence['startdatum']); ?> – <?php echo htmlspecialchars($absence['enddatum']); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Keine Abwesenheiten</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section id="tuev" class="widget card">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-shield-check"></i> Fällige TÜV</h2>
                    </div>
                    <?php if ($huDueError): ?>
                        <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($huDueError); ?></p>
                    <?php elseif (!empty($huDue)): ?>
                        <ul class="stacked-list">
                            <?php foreach ($huDue as $fahrzeug): ?>
                                <li class="stacked-list-item">
                                    <div class="list-primary"><?php echo htmlspecialchars($fahrzeug['Marke'] . ' ' . $fahrzeug['Modell']); ?></div>
                                    <div class="list-secondary">
                                        <span><i class="bi bi-hash"></i> <?php echo htmlspecialchars($fahrzeug['Konzessionsnummer']); ?></span>
                                        <span><i class="bi bi-calendar-date"></i> <?php echo htmlspecialchars($fahrzeug['HU']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">Keine TÜV-Termine in den nächsten drei Monaten.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="eichung" class="widget card">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-wrench-adjustable"></i> Wartungstermine</h2>
                    </div>
                    <?php if ($upcomingMaintenancesError): ?>
                        <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($upcomingMaintenancesError); ?></p>
                    <?php elseif (!empty($upcomingMaintenances)): ?>
                        <ul class="stacked-list">
                            <?php foreach ($upcomingMaintenances as $fahrzeug): ?>
                                <li class="stacked-list-item">
                                    <div class="list-primary"><?php echo htmlspecialchars($fahrzeug['Marke'] . ' ' . $fahrzeug['Modell']); ?></div>
                                    <div class="list-secondary">
                                        <span><i class="bi bi-hash"></i> <?php echo htmlspecialchars($fahrzeug['Konzessionsnummer']); ?></span>
                                        <span><i class="bi bi-calendar-date"></i> <?php echo htmlspecialchars($fahrzeug['FormattedDate']); ?></span>
                                    </div>
                                    <?php if (!empty($fahrzeug['Werkstatt'])): ?>
                                        <div class="list-meta"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($fahrzeug['Werkstatt']); ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">Keine geplanten Wartungen in den nächsten drei Monaten.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="pschein" class="widget card">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-person-badge"></i> Bald ablaufende P-Scheine</h2>
                    </div>
                    <?php if ($pscheinError): ?>
                        <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($pscheinError); ?></p>
                    <?php elseif (!empty($pscheinDue)): ?>
                        <ul class="stacked-list">
                            <?php foreach ($pscheinDue as $fahrer): ?>
                                <li class="stacked-list-item">
                                    <div class="list-primary"><?php echo htmlspecialchars($fahrer['Name']); ?></div>
                                    <div class="list-secondary">
                                        <span><i class="bi bi-calendar-date"></i> gültig bis <?php echo htmlspecialchars($fahrer['PScheinGueltigkeit']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">Alle P-Scheine sind aktuell.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="zentraler-dienstplan" class="widget card grid-span-2">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-clock"></i> Zentraler Dienstplan</h2>
                        <p class="section-subtitle">Wer ist heute in welcher Schicht eingeteilt?</p>
                    </div>
                    <div id="heutige-schicht" class="mb-4">
                        <h3 class="info-title"><i class="bi bi-sunrise"></i> Heutige Schicht</h3>
                        <?php if ($heutigeSchichtError): ?>
                            <p class="text-danger mb-0">Fehler bei der Abfrage: <?php echo htmlspecialchars($heutigeSchichtError); ?></p>
                        <?php elseif (!empty($heutige_schicht)): ?>
                            <ul class="stacked-list">
                                <?php foreach ($heutige_schicht as $schicht): ?>
                                    <li class="stacked-list-item">
                                        <div class="list-primary"><?php echo htmlspecialchars($schicht['name']); ?></div>
                                        <div class="list-secondary"><span class="badge text-bg-secondary"><?php echo htmlspecialchars($schicht['schicht']); ?></span></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Keine Schichtdaten für heute verfügbar.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="dienstplan-zentrale" class="widget card grid-span-full">
                <div class="card-body">
                    <div class="section-header">
                        <h2 class="section-title"><i class="bi bi-calendar3"></i> Schichtplan Zentrale (nächste Tage)</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle dashboard-table">
                            <thead>
                                <tr>
                                    <th scope="col">Mitarbeiter</th>
                                    <?php foreach ($dates as $date): ?>
                                        <th scope="col" class="<?php echo $date['isWeekend'] ? 'weekend' : ''; ?>"><?php echo htmlspecialchars($date['day']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mitarbeiter)): ?>
                                    <?php foreach ($mitarbeiter as $person): ?>
                                        <tr>
                                            <th scope="row"><?php echo htmlspecialchars($person['nachname'] . ', ' . $person['vorname']); ?></th>
                                            <?php foreach ($dates as $date): ?>
                                                <?php
                                                $cellClass = '';
                                                $cellText  = '-';

                                                if (isset($dienstplanMap[$person['mitarbeiter_id']][$date['date']])) {
                                                    $schicht  = $dienstplanMap[$person['mitarbeiter_id']][$date['date']];
                                                    $cellText = $schicht;
                                                    switch ($schicht) {
                                                        case 'F0':
                                                        case 'F1':
                                                        case 'F3':
                                                            $cellClass = 'early-shift';
                                                            break;
                                                        case 'F2':
                                                            $cellClass = 'mid-shift';
                                                            break;
                                                        case 'S0':
                                                        case 'S1':
                                                            $cellClass = 'late-shift';
                                                            break;
                                                        case 'N':
                                                            $cellClass = 'night-shift';
                                                            break;
                                                    }
                                                }
                                                ?>
                                                <td class="<?php echo $cellClass; ?>"><?php echo htmlspecialchars($cellText); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo count($dates) + 1; ?>" class="text-center text-muted">Keine Mitarbeiter gefunden.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
        <script>
                document.addEventListener("DOMContentLoaded", () => {
                        const greetingMessage = "<?php echo $greeting_message; ?>";
                        const greetingElement = document.getElementById("greeting");
                        let i = 0;

                        function typeGreeting() {
                                if (i < greetingMessage.length) {
                                        greetingElement.textContent += greetingMessage.charAt(i);
                                        i++;
                                        setTimeout(typeGreeting, 100); // Geschwindigkeit des Tippens (100ms pro Buchstabe)
                                }
                        }

                        typeGreeting();
                });
        </script>

    <script>
        const burger = document.querySelector('.burger-menu');
        const navLinks = document.querySelector('.nav-links');
        if (burger && navLinks) {
            burger.addEventListener('click', () => {
                navLinks.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
