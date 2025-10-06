<?php
require_once '../includes/bootstrap.php';
require_once '../includes/absencetypes.php';
session_start();

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Monat und Jahr aus GET-Parametern oder Standardwerte verwenden
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$start_date = "$currentYear-$currentMonth-01";
$end_date = date('Y-m-t', strtotime($start_date));

/* Verwaltungsmitarbeiter mit Abwesenheiten im Zeitraum abrufen */
$stmt = $pdo->prepare("
  SELECT DISTINCT b.BenutzerID, b.Name
  FROM Benutzer b
  JOIN verwaltung_abwesenheit va ON b.BenutzerID = va.mitarbeiter_id
  WHERE (
      (va.startdatum IS NOT NULL AND va.enddatum IS NOT NULL AND va.startdatum <= :end_date AND va.enddatum >= :start_date)
      OR (va.datum BETWEEN :start_date AND :end_date)
  )
  ORDER BY b.Name ASC
");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alle Benutzer für das Formular abrufen
$benutzerStmt = $pdo->query("SELECT BenutzerID, Name FROM Benutzer ORDER BY Name ASC");
$benutzerListe = $benutzerStmt->fetchAll(PDO::FETCH_ASSOC);

// Wochentage für den aktuellen Monat vorbereiten
$dates = [];
for ($day = 1; $day <= date('t', strtotime($start_date)); $day++) {
    $timestamp = strtotime("$currentYear-$currentMonth-$day");
    $dates[] = [
        'day' => $day,
        'isWeekend' => in_array(date('N', $timestamp), [6, 7]),
        'date' => date('Y-m-d', $timestamp),
    ];
}

// Abwesenheiten im Zeitraum abrufen
$abwesenheitenStmt = $pdo->prepare("
  SELECT *
  FROM verwaltung_abwesenheit
  WHERE (
      (startdatum IS NOT NULL AND enddatum IS NOT NULL AND startdatum <= :end_date AND enddatum >= :start_date)
      OR (datum BETWEEN :start_date AND :end_date)
  )
");
$abwesenheitenStmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$abwesenheiten = $abwesenheitenStmt->fetchAll(PDO::FETCH_ASSOC);

// Monatnavigation vorbereiten
$prevMonth = date('m', strtotime('-1 month', strtotime($start_date)));
$prevYear = date('Y', strtotime('-1 month', strtotime($start_date)));
$nextMonth = date('m', strtotime('+1 month', strtotime($start_date)));
$nextYear = date('Y', strtotime('+1 month', strtotime($start_date)));

// Deutsche Monatsnamen formatieren
setlocale(LC_TIME, 'de_DE.UTF-8');
$formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$formatter->setPattern('MMMM yyyy');

// Ungenehmigte Abwesenheiten anzeigen, wenn Benutzer Zugriff hat
$rollen = array_map('trim', (array)$sekundarRolle);
$anzeigenAbwesenheiten = in_array('Verwaltung', $rollen, true);
$offeneAbwesenheiten = [];

if ($anzeigenAbwesenheiten) {
    try {
        $stmt = $pdo->prepare("SELECT va.*, b.Name FROM verwaltung_abwesenheit va JOIN Benutzer b ON va.mitarbeiter_id = b.BenutzerID WHERE va.typ NOT IN ('Krank', 'Kind Krank') AND va.genehmigt_am IS NULL ORDER BY b.Name, va.typ, COALESCE(va.startdatum, va.datum) ASC");
        $stmt->execute();
        $rohdaten = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rohdaten as $eintrag) {
            $offeneAbwesenheiten[] = [
                'Name' => $eintrag['Name'],
                'typ' => $eintrag['typ'],
                'beschreibung' => $eintrag['beschreibung'],
                'von' => $eintrag['startzeit'],
                'bis' => $eintrag['endzeit'],
                'start' => $eintrag['startdatum'] ?? $eintrag['datum'],
                'end' => $eintrag['enddatum'] ?? $eintrag['datum'],
                'ids' => [$eintrag['id']]
            ];
        }
    } catch (PDOException $e) {
        die("Fehler beim Abrufen der Abwesenheiten: " . $e->getMessage());
    }
}

// CSS-Klassen für Abwesenheitstypen definieren
function getAbwesenheitsKlasse($typ) {
    switch ($typ) {
        case 'Krank':
        case 'Kind Krank':
            return 'absent-green';
        case 'Urlaub':
            return 'absent-blue';
        case 'Kommt später':
            return 'absent-yellow';
        case 'Geht eher':
            return 'absent-orange';
        case 'Unterbrechung':
            return 'absent-purple';
        default:
            return 'absent-red';
    }
}

function getAbwesenheitsKuerzel($typ) {
    switch ($typ) {
        case 'Krank':
            return 'KR';
        case 'Kind Krank':
            return 'KK';
        case 'Urlaub':
            return 'UR';
        case 'Kommt später':
            return 'LS';
        case 'Geht eher':
            return 'GE';
        case 'Unterbrechung':
            return 'UB';
        default:
            return '?';
    }
}

function getAbwesenheitsTooltip(array $abwesenheit) {
    $beschreibung = trim((string)($abwesenheit['beschreibung'] ?? ''));
    $typ = $abwesenheit['typ'] ?? '';
    $lines = [];

    switch ($typ) {
        case 'Geht eher':
            if (!empty($abwesenheit['endzeit'])) {
                $lines[] = 'Uhrzeit: ' . $abwesenheit['endzeit'];
            }
            if ($beschreibung !== '') {
                $lines[] = 'Grund: ' . $beschreibung;
            }
            break;
        case 'Kommt später':
            if (!empty($abwesenheit['startzeit'])) {
                $lines[] = 'Uhrzeit: ' . $abwesenheit['startzeit'];
            }
            if ($beschreibung !== '') {
                $lines[] = 'Grund: ' . $beschreibung;
            }
            break;
        case 'Unterbrechung':
            if (!empty($abwesenheit['datum'])) {
                $lines[] = 'Datum: ' . $abwesenheit['datum'];
            }
            $zeitraum = [];
            if (!empty($abwesenheit['startzeit'])) {
                $zeitraum[] = $abwesenheit['startzeit'];
            }
            if (!empty($abwesenheit['endzeit'])) {
                $zeitraum[] = $abwesenheit['endzeit'];
            }
            if (!empty($zeitraum)) {
                $lines[] = 'Zeit: ' . implode(' bis ', $zeitraum);
            }
            if ($beschreibung !== '') {
                $lines[] = 'Beschreibung: ' . $beschreibung;
            }
            break;
        case 'Urlaub':
            $lines[] = 'Typ: ' . $typ;
            $zeitraum = [];
            if (!empty($abwesenheit['startdatum'])) {
                $zeitraum[] = $abwesenheit['startdatum'];
            }
            if (!empty($abwesenheit['enddatum'])) {
                $zeitraum[] = $abwesenheit['enddatum'];
            }
            if (!empty($zeitraum)) {
                $lines[] = 'Zeitraum: ' . implode(' bis ', $zeitraum);
            }
            break;
        case 'Kind Krank':
            $zeitraum = [];
            if (!empty($abwesenheit['startdatum'])) {
                $zeitraum[] = $abwesenheit['startdatum'];
            }
            if (!empty($abwesenheit['enddatum'])) {
                $zeitraum[] = $abwesenheit['enddatum'];
            }
            if (!empty($zeitraum)) {
                $lines[] = 'Zeitraum: ' . implode(' bis ', $zeitraum);
            }
            break;
        default:
            if ($beschreibung !== '') {
                $lines[] = $beschreibung;
            }
            break;
    }

    return implode("\n", array_filter($lines));
}
?>
<?php
$title = 'Verwaltung Abwesenheiten';
include __DIR__ . '/../includes/layout.php';
?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script>
    const typesWithPeriod = <?= json_encode($ABSENCE_TYPES['period']); ?>;
    const typesWithTimePoint = <?= json_encode($ABSENCE_TYPES['time_point']); ?>;
    const typesWithTimeRange = <?= json_encode($ABSENCE_TYPES['time_range']); ?>;
    function updateFormFields() {
      const typ = document.getElementById('typ').value;
      const isPeriod = typesWithPeriod.includes(typ);
      const isTimePoint = typesWithTimePoint.includes(typ);
      const isTimeRange = typesWithTimeRange.includes(typ);
      document.getElementById('zeitraum').style.display = isPeriod ? 'block' : 'none';
      document.getElementById('zeitpunkt').style.display = isTimePoint ? 'block' : 'none';
      document.getElementById('zeitspanne').style.display = isTimeRange ? 'block' : 'none';
      document.getElementById('startdatum').disabled = !isPeriod;
      document.getElementById('enddatum').disabled = !isPeriod;
      document.getElementById('tag_zeitpunkt').disabled = !isTimePoint;
      document.getElementById('zeit').disabled = !isTimePoint;
      document.getElementById('tag_zeitspanne').disabled = !isTimeRange;
      document.getElementById('von_uhrzeit').disabled = !isTimeRange;
      document.getElementById('bis_uhrzeit').disabled = !isTimeRange;
    }
    function openAbwesenheitModal() {
      document.getElementById('abwesenheitModal').style.display = 'flex';
    }
    function closeAbwesenheitModal() {
      document.getElementById('abwesenheitModal').style.display = 'none';
    }
    window.addEventListener('load', updateFormFields);
  </script>
  <style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .dashboard-table td {
        text-align: center;
        padding: 5px;
        border: 1px solid #ccc;
    }
    .weekend {
        background-color: #f8d7da;
    }
    .absent-green {
        background-color: #d4edda;
        color: #155724;
        font-weight: bold;
    }
    .absent-blue {
        background-color: #cce5ff;
        color: #004085;
        font-weight: bold;
    }
    .absent-red {
        background-color: #f8d7da;
        color: #721c24;
        font-weight: bold;
    }
	</style>

    <main>
    <h1>Abwesenheiten Verwaltung</h1>

    <?php if ($anzeigenAbwesenheiten): ?>
        <h2>Offene Abwesenheiten</h2>

        <?php if (!empty($offeneAbwesenheiten)): ?>
                <table>
                        <thead>
                                <tr>
                                        <th>Mitarbeiter</th>
                                        <th>Zeitraum</th>
                                        <th>Typ</th>
                                        <th>Von</th>
                                        <th>Bis</th>
                                        <th>Beschreibung</th>
                                        <th>Aktion</th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php foreach ($offeneAbwesenheiten as $eintrag): ?>
                                        <tr>
                                                <td><?= htmlspecialchars((string)$eintrag['Name']) ?></td>
                                                <td><?= htmlspecialchars($eintrag['start']) ?> bis <?= htmlspecialchars($eintrag['end']) ?></td>
                                                <td><?= htmlspecialchars($eintrag['typ']) ?></td>
                                                <td><?= htmlspecialchars((string)$eintrag['von']) ?></td>
                                                <td><?= htmlspecialchars((string)$eintrag['bis']) ?></td>
                                                <td><?= htmlspecialchars((string)$eintrag['beschreibung']) ?></td>
                                                <td>
                                                        <form action="verwaltung_abwesenheit_status.php" method="post" style="display:inline-block;">
                                                                <?php foreach ($eintrag['ids'] as $id): ?>
                                                                        <input type="hidden" name="abwesenheit_ids[]" value="<?= $id ?>">
                                                                <?php endforeach; ?>
                                                                <button type="submit" name="action" value="approve">Genehmigen</button>
                                                                <button type="submit" name="action" value="reject">Ablehnen</button>
                                                        </form>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                        </tbody>
                </table>
        <?php else: ?>
            <p>Keine offenen Abwesenheiten vorhanden.</p>
        <?php endif; ?>
    <?php endif; ?>

    <button onclick="openAbwesenheitModal()">Abwesenheit eintragen</button>

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
			<th class="<?= $date['isWeekend'] ? 'weekend' : '' ?>"><?= $date['day'] ?>.</th>
		  <?php endforeach; ?>
		</tr>
	  </thead>
	  <tbody>
		<?php foreach ($mitarbeiter as $person): ?>
		  <tr>
			<td><?= htmlspecialchars($person['Name']) ?></td>
                        <?php foreach ($dates as $date): ?>
                          <?php
                                $cellClass = $date['isWeekend'] ? 'weekend' : '';
                                $cellText = '-';
                                $cellTooltip = '';
                                foreach ($abwesenheiten as $a) {
                                  if ($a['mitarbeiter_id'] == $person['BenutzerID']) {
                                        $currentDate = $date['date'];
                                        if ((isset($a['datum']) && $a['datum'] === $currentDate) ||
                                            (isset($a['startdatum'], $a['enddatum']) && $currentDate >= $a['startdatum'] && $currentDate <= $a['enddatum'])) {
                                            $cellClass = getAbwesenheitsKlasse($a['typ']);
                                            $cellText = getAbwesenheitsKuerzel($a['typ']);
                                            $cellTooltip = getAbwesenheitsTooltip($a);
                                            break;
                                        }
                                  }
                                }
                                $titleAttr = '';
                                if ($cellTooltip !== '') {
                                  $escapedTooltip = htmlspecialchars($cellTooltip, ENT_QUOTES);
                                  $escapedTooltip = str_replace("\n", '&#10;', $escapedTooltip);
                                  $titleAttr = ' title="' . $escapedTooltip . '"';
                                }
                          ?>
                          <td class="<?= $cellClass ?>"<?= $titleAttr ?>><?= htmlspecialchars($cellText) ?></td>
                        <?php endforeach; ?>
		  </tr>
		<?php endforeach; ?>
	  </tbody>
	</table>
  </main>

	<!-- Modal zur Abwesenheitseintragung -->
        <div id="abwesenheitModal" class="modal-overlay" style="display:none;">
          <div style="background:#fff; padding:20px; border-radius:10px; width:450px;">
                <h2>Abwesenheit eintragen</h2>
                <form action="verwaltung_abwesenheit_eintragen.php" method="post">
		  <label for="mitarbeiter_id">Mitarbeiter:</label>
		  <select name="mitarbeiter_id" required>
			<?php foreach ($benutzerListe as $benutzer): ?>
			  <option value="<?= $benutzer['BenutzerID'] ?>"><?= htmlspecialchars($benutzer['Name']) ?></option>
			<?php endforeach; ?>
		  </select><br><br>

                  <label for="typ">Typ:</label>
                  <select name="typ" id="typ" onchange="updateFormFields()" required>
                        <?php foreach ($ALL_ABSENCE_TYPES as $type): ?>
                          <option value="<?= $type ?>"><?= htmlspecialchars($ABSENCE_TYPE_LABELS[$type]) ?></option>
                        <?php endforeach; ?>
                  </select><br><br>

                  <div id="zeitraum" style="display:none">
                        <label>Von (Datum):</label>
                        <input type="date" name="startdatum" id="startdatum" disabled><br>
                        <label>Bis (Datum):</label>
                        <input type="date" name="enddatum" id="enddatum" disabled><br><br>
                  </div>

                  <div id="zeitpunkt" style="display:none">
                        <label>Tag:</label>
                        <input type="date" name="tag" id="tag_zeitpunkt" disabled><br>
                        <label>Zeit:</label>
                        <input type="time" name="zeit" id="zeit" disabled><br><br>
                  </div>

                  <div id="zeitspanne" style="display:none">
                        <label>Tag:</label>
                        <input type="date" name="tag" id="tag_zeitspanne" disabled><br>
                        <label>Von (Uhrzeit):</label>
                        <input type="time" name="von_uhrzeit" id="von_uhrzeit" disabled><br>
                        <label>Bis (Uhrzeit):</label>
                        <input type="time" name="bis_uhrzeit" id="bis_uhrzeit" disabled><br><br>
                  </div>

		  <label for="beschreibung">Beschreibung:</label>
		  <textarea name="beschreibung" rows="3"></textarea><br><br>

                  <button type="submit">Speichern</button>
                  <button type="button" onclick="closeAbwesenheitModal()">Abbrechen</button>
                </form>
          </div>
        </div>


</body>
</html>
