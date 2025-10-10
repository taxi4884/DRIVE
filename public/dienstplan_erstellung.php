<?php
require_once '../includes/bootstrap.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Monat und Jahr aus GET-Parametern oder Standardwerte verwenden
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$start_date = "$currentYear-$currentMonth-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Vorheriger und nächster Monat berechnen
$prevMonth = date('m', strtotime('-1 month', strtotime($start_date)));
$prevYear = date('Y', strtotime('-1 month', strtotime($start_date)));
$nextMonth = date('m', strtotime('+1 month', strtotime($start_date)));
$nextYear = date('Y', strtotime('+1 month', strtotime($start_date)));

// Deutsche Monatsnamen
setlocale(LC_TIME, 'de_DE.UTF-8');
$currentMonthName = date('F Y', strtotime($start_date));

// Mitarbeiter abrufen
$stmt = $pdo->query("SELECT mitarbeiter_id, vorname, nachname FROM mitarbeiter_zentrale WHERE status = 'Aktiv' ORDER BY nachname ASC");
$mitarbeiterListe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Schichten abrufen
$stmt = $pdo->query("SELECT schicht_id, name, startzeit, endzeit, pause FROM schichten ORDER BY startzeit ASC");
$schichtenListe = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * @param string $time
 * @return DateTime|null
 */
function createTimeReference(string $time): ?DateTime
{
    $formats = ['H:i:s', 'H:i'];
    foreach ($formats as $format) {
        $dateTime = DateTime::createFromFormat($format, $time, new DateTimeZone('UTC'));
        if ($dateTime !== false) {
            return $dateTime;
        }
    }

    return null;
}

function calculateShiftDurationMinutes(string $startzeit, string $endzeit, $pauseMinutes): int
{
    $start = createTimeReference($startzeit);
    $end = createTimeReference($endzeit);

    if (!$start || !$end) {
        return 0;
    }

    $startTimestamp = $start->getTimestamp();
    $endTimestamp = $end->getTimestamp();

    if ($endTimestamp <= $startTimestamp) {
        $endTimestamp += 24 * 60 * 60; // Tageswechsel berücksichtigen
    }

    $durationMinutes = (int) round(($endTimestamp - $startTimestamp) / 60);
    $pauseMinutes = (int) $pauseMinutes;

    $netMinutes = $durationMinutes - $pauseMinutes;

    return $netMinutes > 0 ? $netMinutes : 0;
}

function formatMinutesToHours(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return sprintf('%d:%02d Std.', $hours, $remainingMinutes);
}

$schichtenMap = [];
foreach ($schichtenListe as $schicht) {
    $schicht['duration_minutes'] = calculateShiftDurationMinutes($schicht['startzeit'], $schicht['endzeit'], $schicht['pause']);
    $schichtenMap[$schicht['schicht_id']] = $schicht;
}

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

// Dienstplan speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['schicht'] as $mitarbeiter_id => $tage) {
        foreach ($tage as $datum => $schicht_id) {
            if (!empty($schicht_id)) {
                if ($schicht_id === 'U') {
                    // Urlaub eintragen
                    $stmt = $pdo->prepare("SELECT * FROM abwesenheiten_zentrale WHERE mitarbeiter_id = :mitarbeiter_id AND typ = 'Urlaub' AND startdatum = :datum");
                    $stmt->execute(['mitarbeiter_id' => $mitarbeiter_id, 'datum' => $datum]);

                    if ($stmt->rowCount() === 0) {
                        $stmt = $pdo->prepare("INSERT INTO abwesenheiten_zentrale (mitarbeiter_id, typ, startdatum, enddatum) VALUES (:mitarbeiter_id, 'Urlaub', :datum, :datum)");
                        $stmt->execute(['mitarbeiter_id' => $mitarbeiter_id, 'datum' => $datum]);
                    }
                } else {
                    // Schicht eintragen oder aktualisieren
                    $stmt = $pdo->prepare("SELECT * FROM dienstplan WHERE mitarbeiter_id = :mitarbeiter_id AND datum = :datum");
                    $stmt->execute(['mitarbeiter_id' => $mitarbeiter_id, 'datum' => $datum]);

                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("UPDATE dienstplan SET schicht_id = :schicht_id WHERE mitarbeiter_id = :mitarbeiter_id AND datum = :datum");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO dienstplan (mitarbeiter_id, datum, schicht_id) VALUES (:mitarbeiter_id, :datum, :schicht_id)");
                    }
                    $stmt->execute(['mitarbeiter_id' => $mitarbeiter_id, 'datum' => $datum, 'schicht_id' => $schicht_id]);
                }
            } else {
                // Schicht löschen, wenn leer
                $stmt = $pdo->prepare("DELETE FROM dienstplan WHERE mitarbeiter_id = :mitarbeiter_id AND datum = :datum");
                $stmt->execute(['mitarbeiter_id' => $mitarbeiter_id, 'datum' => $datum]);
            }
        }
    }
    $success = 'Dienstplan erfolgreich gespeichert.';
}

// Bestehende Eintragungen auslesen
$dienstplanData = [];
$stmt = $pdo->prepare("SELECT mitarbeiter_id, datum, schicht_id FROM dienstplan WHERE datum BETWEEN :start_date AND :end_date");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dienstplanData[$row['mitarbeiter_id']][$row['datum']] = $row['schicht_id'];
}

$abwesenheitenData = [];
$stmt = $pdo->prepare("SELECT mitarbeiter_id, startdatum, enddatum FROM abwesenheiten_zentrale WHERE typ = 'Urlaub' AND startdatum BETWEEN :start_date AND :end_date");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentDate = $row['startdatum'];
    while ($currentDate <= $row['enddatum']) {
        $abwesenheitenData[$row['mitarbeiter_id']][$currentDate] = 'U';
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }
}

$arbeitszeitSummen = [];
foreach ($mitarbeiterListe as $mitarbeiter) {
    $mitarbeiterId = $mitarbeiter['mitarbeiter_id'];
    $arbeitszeitSummen[$mitarbeiterId] = 0;

    foreach ($dates as $date) {
        $datum = $date['date'];
        $schichtId = $dienstplanData[$mitarbeiterId][$datum] ?? null;

        if ($schichtId && isset($schichtenMap[$schichtId])) {
            $arbeitszeitSummen[$mitarbeiterId] += $schichtenMap[$schichtId]['duration_minutes'];
        }
    }
}

$gesamtArbeitszeit = array_sum($arbeitszeitSummen);
?>
<?php
$title = 'Dienstplan erstellen';
include __DIR__ . '/../includes/layout.php';
?>
    <style>
        .success {
            color: green;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table th, table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }
        table th {
            background-color: #007bff;
            color: white;
        }
        table .weekend {
            background-color: #f8d7da;
            color: #721c24;
        }
        table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .dienstplan-form button {
            margin-top: 15px;
        }
        .arbeitszeit-summary {
            margin-top: 30px;
            background: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }
        .arbeitszeit-summary h2 {
            margin-top: 0;
            font-size: 18px;
        }
        .arbeitszeit-summary table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            box-shadow: none;
        }
        .arbeitszeit-summary table th,
        .arbeitszeit-summary table td {
            text-align: left;
            border: 1px solid #ddd;
            padding: 8px;
        }
        .arbeitszeit-summary table th {
            background-color: #f1f1f1;
            color: #333;
        }
        .arbeitszeit-summary table tr:nth-child(even) {
            background-color: #fafafa;
        }
        .arbeitszeit-summary table tr.arbeitszeit-summary-total th {
            background-color: #007bff;
            color: #fff;
        }
        select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .month-navigation {
            margin-bottom: 20px;
            text-align: left;
        }
        .month-navigation a {
            padding: 10px 15px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 5px;
        }
        .month-navigation a:hover {
            background-color: #0056b3;
        }
        .month-navigation span {
            font-size: 18px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>

        <main>
      <h1>Dienstplan erstellen</h1>
  
      <?php if (isset($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>
  
      <!-- Monat wechseln -->
      <div class="month-navigation">
          <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&laquo; Vorheriger Monat</a>
          <span><?= ucfirst(date('F Y', strtotime($start_date))) ?></span>
          <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Nächster Monat &raquo;</a>
      </div>
  
      <form method="POST" class="dienstplan-form">
          <table>
              <thead>
                  <tr>
                      <th>Mitarbeiter</th>
                      <?php foreach ($dates as $date): ?>
                          <th class="<?php echo $date['isWeekend'] ? 'weekend' : ''; ?>">
                              <?php echo $date['day']; ?>
                          </th>
                      <?php endforeach; ?>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($mitarbeiterListe as $mitarbeiter): ?>
                      <tr>
                          <td><?= htmlspecialchars($mitarbeiter['nachname'] . ', ' . $mitarbeiter['vorname']) ?></td>
                          <?php foreach ($dates as $date): ?>
                              <td>
                                  <?php
                                  $selectedValue = $dienstplanData[$mitarbeiter['mitarbeiter_id']][$date['date']] ??
                                                   $abwesenheitenData[$mitarbeiter['mitarbeiter_id']][$date['date']] ?? '';
                                  ?>
                                  <select name="schicht[<?= $mitarbeiter['mitarbeiter_id'] ?>][<?= $date['date'] ?>]">
                                      <option value="">-</option>
                                      <?php foreach ($schichtenListe as $schicht): ?>
                                          <option value="<?= $schicht['schicht_id'] ?>" <?= $selectedValue == $schicht['schicht_id'] ? 'selected' : '' ?>>
                                              <?= htmlspecialchars($schicht['name']) ?>
                                          </option>
                                      <?php endforeach; ?>
                                      <option value="U" <?= $selectedValue === 'U' ? 'selected' : '' ?>>Urlaub</option>
                                  </select>
                              </td>
                          <?php endforeach; ?>
                      </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
          <button type="submit">Speichern</button>
      </form>
      <section class="arbeitszeit-summary">
          <h2>Arbeitszeit-Zusammenfassung</h2>
          <table>
              <thead>
                  <tr>
                      <th>Mitarbeiter</th>
                      <th>Arbeitszeit</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($mitarbeiterListe as $mitarbeiter): ?>
                      <?php
                          $mitarbeiterId = $mitarbeiter['mitarbeiter_id'];
                          $arbeitszeit = $arbeitszeitSummen[$mitarbeiterId] ?? 0;
                      ?>
                      <tr>
                          <td><?= htmlspecialchars($mitarbeiter['nachname'] . ', ' . $mitarbeiter['vorname']) ?></td>
                          <td><?= formatMinutesToHours($arbeitszeit) ?></td>
                      </tr>
                  <?php endforeach; ?>
              </tbody>
              <tfoot>
                  <tr class="arbeitszeit-summary-total">
                      <th>Gesamt</th>
                      <th><?= formatMinutesToHours((int) $gesamtArbeitszeit) ?></th>
                  </tr>
              </tfoot>
          </table>
      </section>
    </main>

</body>
</html>
