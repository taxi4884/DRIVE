<?php
require_once '../includes/bootstrap.php';
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
  WHERE va.datum BETWEEN :start_date AND :end_date
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
  WHERE datum BETWEEN :start_date AND :end_date
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
$anzeigenAbwesenheiten = false;
$offeneAbwesenheiten = [];

try {
    $stmt = $pdo->prepare("SELECT AbwesenheitVerwaltung FROM Benutzer WHERE BenutzerID = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['AbwesenheitVerwaltung'] == 1) {
        $anzeigenAbwesenheiten = true;
    }
} catch (PDOException $e) {
    die("Fehler beim Prüfen der Berechtigung: " . $e->getMessage());
}

if ($anzeigenAbwesenheiten) {
    try {
        $stmt = $pdo->prepare("SELECT va.*, b.Name FROM verwaltung_abwesenheit va JOIN Benutzer b ON va.mitarbeiter_id = b.BenutzerID WHERE va.typ NOT IN ('Krank', 'Kind Krank') AND va.genehmigt_am IS NULL ORDER BY b.Name, va.typ, va.datum ASC");
        $stmt->execute();
        $rohdaten = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Abwesenheiten nach Benutzer, Typ und zusammenhängenden Tagen gruppieren
        foreach ($rohdaten as $eintrag) {
            $key = $eintrag['mitarbeiter_id'] . '|' . $eintrag['typ'] . '|' . $eintrag['beschreibung'];
            $datum = $eintrag['datum'];

            if (!isset($offeneAbwesenheiten[$key])) {
                $offeneAbwesenheiten[$key] = [
                    'Name' => $eintrag['Name'],
                    'typ' => $eintrag['typ'],
                    'beschreibung' => $eintrag['beschreibung'],
                    'von' => $eintrag['von'],
                    'bis' => $eintrag['bis'],
                    'start' => $datum,
                    'end' => $datum,
                    'ids' => [$eintrag['id']]
                ];
            } else {
                $prevDate = new DateTime($offeneAbwesenheiten[$key]['end']);
                $currentDate = new DateTime($datum);
                $diff = $prevDate->diff($currentDate)->days;

                if ($diff <= 1) {
                    $offeneAbwesenheiten[$key]['end'] = $datum;
                    $offeneAbwesenheiten[$key]['ids'][] = $eintrag['id'];
                } else {
                    $key .= uniqid();
                    $offeneAbwesenheiten[$key] = [
                        'Name' => $eintrag['Name'],
                        'typ' => $eintrag['typ'],
                        'beschreibung' => $eintrag['beschreibung'],
                        'von' => $eintrag['von'],
                        'bis' => $eintrag['bis'],
                        'start' => $datum,
                        'end' => $datum,
                        'ids' => [$eintrag['id']]
                    ];
                }
            }
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verwaltung Abwesenheiten | DRIVE</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <script>
    function updateFormFields() {
      const typ = document.getElementById('typ').value;
      document.getElementById('zeitraum').style.display = (typ === 'Urlaub' || typ === 'Krank' || typ === 'Kind Krank') ? 'block' : 'none';
      const showTime = (typ === 'Kommt später' || typ === 'Geht eher');
      document.getElementById('uhrzeit_eintrag').style.display = showTime ? 'block' : 'none';
      document.getElementById('zeitpunkt_datum').style.display = showTime ? 'inline-block' : 'none';
      document.getElementById('zeitspanne').style.display = (typ === 'Unterbrechung') ? 'block' : 'none';
    }
  </script>
  <style>
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
</head>
<body>
  <?php include 'nav.php'; ?>
  <main>
    <h1>Abwesenheiten Verwaltung</h1>
	
	<h2>Offene Abwesenheiten</h2>

	<?php if ($anzeigenAbwesenheiten && !empty($offeneAbwesenheiten)): ?>
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
	<?php elseif ($anzeigenAbwesenheiten): ?>
    <p>Keine offenen Abwesenheiten vorhanden.</p>
	<?php endif; ?>

    <button onclick="document.getElementById('abwesenheitModal').style.display='block'">Abwesenheit eintragen</button>

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

				foreach ($abwesenheiten as $a) {
				  if ($a['mitarbeiter_id'] == $person['BenutzerID'] && $a['datum'] == $date['date']) {
                                        $cellClass = getAbwesenheitsKlasse($a['typ']);
                                        $cellText = getAbwesenheitsKuerzel($a['typ']);
					break;
				  }
				}
			  ?>
			  <td class="<?= $cellClass ?>"><?= htmlspecialchars($cellText) ?></td>
			<?php endforeach; ?>
		  </tr>
		<?php endforeach; ?>
	  </tbody>
	</table>
  </main>

	<!-- Modal zur Abwesenheitseintragung -->
	<div id="abwesenheitModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center;">
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
			<option value="Urlaub">Urlaub</option>
			<option value="Krank">Krank</option>
			<option value="Kind Krank">Kind Krank</option>
			<option value="Kommt später">Kommt später</option>
			<option value="Geht eher">Geht eher</option>
			<option value="Unterbrechung">Abwesend über den Tag</option>
		  </select><br><br>

		  <div id="zeitraum" style="display:none">
			<label>Von (Datum):</label>
			<input type="date" name="von_datum"><br>
			<label>Bis (Datum):</label>
			<input type="date" name="bis_datum"><br><br>
		  </div>

                  <div id="uhrzeit_eintrag" style="display:none">
                        <label>Datum:</label>
                        <input type="date" name="zeitpunkt_datum" id="zeitpunkt_datum"><br>
                        <label>Uhrzeit:</label>
                        <input type="time" name="zeitpunkt"><br><br>
                  </div>

		  <div id="zeitspanne" style="display:none">
			<label>Datum:</label>
			<input type="date" name="tag_zeitspanne"><br>
			<label>Von (Uhrzeit):</label>
			<input type="time" name="von_uhrzeit"><br>
			<label>Bis (Uhrzeit):</label>
			<input type="time" name="bis_uhrzeit"><br><br>
		  </div>

		  <label for="beschreibung">Beschreibung:</label>
		  <textarea name="beschreibung" rows="3"></textarea><br><br>

		  <button type="submit">Speichern</button>
		  <button type="button" onclick="document.getElementById('abwesenheitModal').style.display='none'">Abbrechen</button>
		</form>
	  </div>
	</div>

</body>
</html>