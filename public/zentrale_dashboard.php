<?php
// zentrale_dashboard.php

require_once '../includes/bootstrap.php';
require_once '../includes/date_utils.php';
require_once 'modals/process_abwesenheit.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Monat und Jahr aus GET-Parametern oder Standardwerte verwenden
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$start_date = "$currentYear-$currentMonth-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Mitarbeiter abrufen, sortiert nach Nachnamen
$stmt = $pdo->prepare("SELECT vorname, nachname, mitarbeiter_id FROM mitarbeiter_zentrale ORDER BY nachname ASC");
$stmt->execute();
$mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Wochentage für den aktuellen Monat vorbereiten
$dates = [];
for ($day = 1; $day <= date('t', strtotime($start_date)); $day++) {
    $timestamp = strtotime("$currentYear-$currentMonth-$day");
    $dates[] = [
        'day' => $day,
        'isWeekend' => in_array(date('N', $timestamp), [6, 7]), // Samstag oder Sonntag
        'date' => date('Y-m-d', $timestamp),
    ];
}

// Abwesenheiten abrufen
$abwesenheitenStmt = $pdo->prepare("SELECT mitarbeiter_id, typ, startdatum, enddatum FROM abwesenheiten_zentrale WHERE startdatum <= :end_date AND enddatum >= :start_date");
$abwesenheitenStmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$abwesenheiten = $abwesenheitenStmt->fetchAll(PDO::FETCH_ASSOC);

// Vorheriger und nächster Monat berechnen
$prevMonth = date('m', strtotime('-1 month', strtotime($start_date)));
$prevYear = date('Y', strtotime('-1 month', strtotime($start_date)));
$nextMonth = date('m', strtotime('+1 month', strtotime($start_date)));
$nextYear = date('Y', strtotime('+1 month', strtotime($start_date)));

// Deutsche Monatsnamen
setlocale(LC_TIME, 'de_DE.UTF-8');
$formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$formatter->setPattern('MMMM yyyy');


// Schichten aus dem Dienstplan abrufen
$stmt = $pdo->prepare("
    SELECT dp.mitarbeiter_id, dp.datum, s.name AS schicht_name
    FROM dienstplan dp
    LEFT JOIN schichten s ON dp.schicht_id = s.schicht_id
    WHERE dp.datum BETWEEN :start_date AND :end_date
");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$dienstplan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dienstplan als assoziatives Array strukturieren
$dienstplanMap = [];
foreach ($dienstplan as $entry) {
    $dienstplanMap[$entry['mitarbeiter_id']][$entry['datum']] = $entry['schicht_name'];
}

// Geburtsdatum abfragen
$birthdaysStmt = $pdo->prepare("
    SELECT vorname, nachname, geburtsdatum, DATE_FORMAT(geburtsdatum, '%d.%m.') AS geburtstag
    FROM mitarbeiter_zentrale
    WHERE geburtsdatum IS NOT NULL
    ORDER BY MONTH(geburtsdatum), DAY(geburtsdatum)
");
$birthdaysStmt->execute();
$birthdays = $birthdaysStmt->fetchAll(PDO::FETCH_ASSOC);

function calculateAge($birthdate, $onDate) {
    $birthDate = new DateTime($birthdate);
    $referenceDate = new DateTime($onDate);
    return $referenceDate->diff($birthDate)->y;
}

foreach ($birthdays as &$birthday) {
    $currentYear = date('Y');
    // Nächstes Geburtstagsdatum im aktuellen Jahr berechnen
    $birthdayDateThisYear = date("$currentYear-m-d", strtotime($birthday['geburtsdatum']));
    $birthday['new_age'] = calculateAge($birthday['geburtsdatum'], $birthdayDateThisYear);
}
unset($birthday); // Referenz zurücksetzen

// Urlaube abfragen und gruppieren
$upcomingVacationsStmt = $pdo->prepare("
    SELECT mz.mitarbeiter_id, mz.vorname, mz.nachname, mz.geburtsdatum, az.startdatum, az.enddatum
    FROM abwesenheiten_zentrale az
    JOIN mitarbeiter_zentrale mz ON az.mitarbeiter_id = mz.mitarbeiter_id
    WHERE az.typ = 'Urlaub'
    AND az.startdatum BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 MONTH
    ORDER BY mz.mitarbeiter_id, az.startdatum
");
$upcomingVacationsStmt->execute();
$upcomingVacations = $upcomingVacationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Gruppierung vorbereiten
$groupedVacations = [];
$currentVacation = null;

foreach ($upcomingVacations as $vacation) {
    $start = $vacation['startdatum'];
    $end = $vacation['enddatum'];

    if (!$currentVacation) {
        $currentVacation = [
            'mitarbeiter_id' => $vacation['mitarbeiter_id'],
            'vorname' => $vacation['vorname'],
            'nachname' => $vacation['nachname'],
            'geburtsdatum' => $vacation['geburtsdatum'],
            'startdatum' => $start,
            'enddatum' => $end
        ];
    } else {
        if (
            $currentVacation['mitarbeiter_id'] === $vacation['mitarbeiter_id'] &&
            $currentVacation['enddatum'] >= date('Y-m-d', strtotime($start . ' -1 day'))
        ) {
            $currentVacation['enddatum'] = max($currentVacation['enddatum'], $end);
        } else {
            $groupedVacations[] = $currentVacation;
            $currentVacation = [
                'mitarbeiter_id' => $vacation['mitarbeiter_id'],
                'vorname' => $vacation['vorname'],
                'nachname' => $vacation['nachname'],
                'geburtsdatum' => $vacation['geburtsdatum'],
                'startdatum' => $start,
                'enddatum' => $end
            ];
        }
    }
}

if ($currentVacation) {
    $groupedVacations[] = $currentVacation;
}

// Ungelesene Krankmeldungen für den aktuellen Benutzer ermitteln
$unreadStmt = $pdo->prepare("
    SELECT 
        az.abwesenheit_id,
        mz.vorname  AS employee_firstname, 
        mz.nachname AS employee_lastname,
        az.startdatum, 
        az.enddatum,
        az.typ
    FROM abwesenheiten_zentrale az
    JOIN mitarbeiter_zentrale mz 
        ON az.mitarbeiter_id = mz.mitarbeiter_id
    JOIN abwesenheiten_read_status ars 
        ON az.abwesenheit_id = ars.abwesenheit_id
    WHERE ars.BenutzerID = :user_id
      AND ars.read_status = 0
      AND az.typ = 'Krank'
");

// Hier nutze den Session-Key 'user_id':
$unreadStmt->execute([
    'user_id' => $_SESSION['user_id']
]);
$unreadAbwesenheiten = $unreadStmt->fetchAll(PDO::FETCH_ASSOC);

// Anzahl ungelesener Einträge:
$unreadCount = count($unreadAbwesenheiten);

?>
<?php
$title = 'Zentrale Dashboard';
include __DIR__ . '/../includes/layout.php';
?>
    <!-- Font Awesome Einbindung -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Bisheriges CSS -->
    <script src="js/modal.js"></script>
    <style>
                /* Krankheitszellen */
		.dashboard-table .absent-sick {
		  background-color: #d4edda;
		  color: #155724;
		}
		/* Urlaubszellen */
		.dashboard-table .absent-vacation {
		  background-color: #fff3cd;
		  color: #856404;
		}
		/*Wochenende*/
		.dashboard-table .weekend {
		  background-color: #f8d7da;
		  color: #721c24;
		}
		.month-navigation {
		  margin: 20px auto;
		  text-align: left;
		}
		.month-navigation a {
		  padding: 10px 15px;
		  font-size: 16px;
		  background-color: #FFD700;
		  color: #000000;
		  text-decoration: none;
		  border-radius: 4px;
		  margin: 0 5px;
		}
		.month-navigation a:hover {
		  background-color: #FFC107;
		}
		.month-navigation span {
		  font-size: 18px;
		  font-weight: bold;
		  margin-left: 10px;
		}
		</style>

        <main class="with_sidebar">
        <h1>Zentrale Dashboard</h1>
		
		<?php if ($unreadCount > 0): ?>
			<section>
				<h3>Neue Krankmeldungen</h3>
				<ul>
					<?php foreach ($unreadAbwesenheiten as $absence): ?>
						<li>
							Mitarbeiter: 
							<?php echo htmlspecialchars($absence['employee_lastname'] . ', ' . $absence['employee_firstname']); ?><br>
							Zeitraum: 
							<?php echo date('d.m.Y', strtotime($absence['startdatum'])); ?>
							bis 
							<?php echo date('d.m.Y', strtotime($absence['enddatum'])); ?><br>
							Grund: 
							<?php echo htmlspecialchars($absence['typ']); ?><br>
							<button onclick="markAsRead(<?php echo $absence['abwesenheit_id']; ?>)">
								Gelesen
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php else: ?>
		<?php endif; ?>

		<button onclick="openModal('abwesenheitModal')">Krank melden</button>

        <!-- Monat wechseln -->
        <div class="month-navigation">
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&laquo; Vorheriger Monat</a>
            <span><?= ucfirst($formatter->format(strtotime($start_date))) ?></span>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Nächster Monat &raquo;</a>
        </div>

        <table class="dashboard-table">
			<thead>
				<tr>
					<th>Mitarbeiter</th>
					<?php foreach ($dates as $date): ?>
						<th class="<?php echo $date['isWeekend'] ? 'weekend' : ''; ?>">
							<?php echo $date['day'] . '.'; ?>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($mitarbeiter)): ?>
					<?php foreach ($mitarbeiter as $person): ?>
						<tr>
							<td><?php echo htmlspecialchars($person['nachname'] . ', ' . $person['vorname']); ?></td>
							<?php foreach ($dates as $date): ?>
								<?php
								// Standardklasse und Text
								$cellClass = '';
								$cellText = '-';

								// Abwesenheiten prüfen
								foreach ($abwesenheiten as $absence) {
									if ($absence['mitarbeiter_id'] == $person['mitarbeiter_id'] && $date['date'] >= $absence['startdatum'] && $date['date'] <= $absence['enddatum']) {
										if ($absence['typ'] === 'Krank') {
											$cellClass = 'absent-sick';
											$cellText = '-'; // Text bleibt unverändert
										} elseif ($absence['typ'] === 'Urlaub') {
											$cellClass = 'absent-vacation';
											$cellText = 'U';
										}
										break;
									}
								}

								// Schicht prüfen, nur wenn kein Urlaub
								if ($cellText === '-' && isset($dienstplanMap[$person['mitarbeiter_id']][$date['date']])) {
									$cellText = $dienstplanMap[$person['mitarbeiter_id']][$date['date']];
								}
								?>
								<td class="<?php echo $cellClass; ?>">
									<?php echo htmlspecialchars($cellText); ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr>
						<td colspan="<?php echo count($dates) + 1; ?>">Keine Mitarbeiter gefunden.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</main>
	<aside class="sidebar">
		<section>
			<h3>Urlaube der nächsten 3 Monate</h3>
			<ul>
				<?php foreach ($groupedVacations as $vacation): ?>
					<?php 
                                            $workdays = workdaysBetween($vacation['startdatum'], $vacation['enddatum']);
						$age = $vacation['geburtsdatum'] 
							? calculateAge($vacation['geburtsdatum'], $vacation['startdatum']) 
							: '-'; 
					?>
					<li>
						<?php echo htmlspecialchars($vacation['vorname'] . ' ' . $vacation['nachname']); ?>: 
						<?php echo date('d.m.Y', strtotime($vacation['startdatum'])); ?> bis 
						<?php echo date('d.m.Y', strtotime($vacation['enddatum'])); ?> 
						(<?php echo $workdays; ?> Tage)
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section>
			<h3>Geburtstagsliste</h3>
			<ul>
				<?php foreach ($birthdays as $birthday): ?>
					<li>
						<?php echo htmlspecialchars($birthday['vorname'] . ' ' . $birthday['nachname']); ?>: 
						<?php echo $birthday['geburtstag']; ?>
						(<?php echo $birthday['new_age']; ?> Jahre)
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</aside>
        <?php include 'modals/add_abwesenheit_modal.php'; ?>
	<script>
		function markAsRead(abwesenheitId) {
			fetch("mark_as_read.php?abwesenheit_id=" + abwesenheitId)
				.then(response => response.text())
				.then(data => {
					console.log("Antwort von mark_as_read:", data); // <-- zum Debuggen
					if (data === "success") {
						location.reload();
					} else {
						alert("Fehler beim Aktualisieren des Lesestatus: " + data);
					}
				});
		}
	</script>
	<script>
	document.querySelector('.burger-menu').addEventListener('click', () => {
	document.querySelector('.nav-links').classList.toggle('active');
	});
	</script>


</body>
</html>
