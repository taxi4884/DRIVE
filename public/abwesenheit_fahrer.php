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

/* 1. Fahrer mit Abwesenheiten im Zeitraum abrufen */
$stmt = $pdo->prepare("
  SELECT DISTINCT f.vorname, f.nachname, f.FahrerID
  FROM Fahrer f
  JOIN FahrerAbwesenheiten fa ON f.FahrerID = fa.FahrerID
  WHERE fa.startdatum <= :end_date AND fa.enddatum >= :start_date AND f.Status IN ('aktiv', 'Aktiv')
  ORDER BY f.nachname ASC
");
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$fahrer = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

/* Abwesenheiten der Fahrer im Zeitraum abrufen */
$abwesenheitenStmt = $pdo->prepare("
  SELECT id, FahrerID, abwesenheitsart, grund, status, startdatum, enddatum, kommentar 
  FROM FahrerAbwesenheiten 
  WHERE startdatum <= :end_date AND enddatum >= :start_date
");
$abwesenheitenStmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$abwesenheiten = $abwesenheitenStmt->fetchAll(PDO::FETCH_ASSOC);

/* 2. Beantragte Urlaube abrufen */
$urlaubAntraegeStmt = $pdo->prepare("
    SELECT fa.id AS abwesenheit_id, f.vorname, f.nachname, fa.startdatum, fa.enddatum, fa.status, fa.kommentar
    FROM FahrerAbwesenheiten fa
    JOIN Fahrer f ON fa.FahrerID = f.FahrerID
    WHERE fa.abwesenheitsart = 'Urlaub' AND fa.status = 'beantragt' AND f.Status IN ('aktiv', 'Aktiv')
    ORDER BY fa.startdatum ASC
");
$urlaubAntraegeStmt->execute();
$urlaubAntraege = $urlaubAntraegeStmt->fetchAll(PDO::FETCH_ASSOC);

// Vorheriger und nächster Monat berechnen
$prevMonth = date('m', strtotime('-1 month', strtotime($start_date)));
$prevYear = date('Y', strtotime('-1 month', strtotime($start_date)));
$nextMonth = date('m', strtotime('+1 month', strtotime($start_date)));
$nextYear = date('Y', strtotime('+1 month', strtotime($start_date)));

// Deutsche Monatsnamen formatieren
setlocale(LC_TIME, 'de_DE.UTF-8');
$formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$formatter->setPattern('MMMM yyyy');
?>
<?php
$title = 'Fahrer Abwesenheiten';
include __DIR__ . '/../includes/layout.php';
?>
  <script src="js/modal.js"></script>
  <style>
    /* Basisstil für Kalenderzellen */
	.dashboard-table td {
	  text-align: center;
	  padding: 5px;
	  border: 1px solid #ddd;
	  transition: transform 0.2s, box-shadow 0.2s;
	}
	/* Hover-Effekt für Zellen */
	.dashboard-table td:hover {
	  transform: scale(1.05);
	  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
	}
	/* Farben und Stil für Krankheitsfälle */
	.absent-krank, .absent-kind-krank {
	  background: linear-gradient(135deg, #0044cc, #3366ff); /* dunkler bis heller Blauverlauf */
	  color: white;
	  border-radius: 4px;
	  font-weight: bold;
	  box-shadow: inset 0 0 5px rgba(0,0,0,0.3);
	}
	/* Farben und Stil für Urlaubsfälle */
	.absent-vacation-beantragt {
	  background: linear-gradient(135deg, #ffcc00, #ffff66); /* gelber Verlauf */
	  color: black;
	  border-radius: 4px;
	  font-weight: bold;
	  box-shadow: inset 0 0 5px rgba(0,0,0,0.3);
	}
	.absent-vacation-genehmigt {
	  background: linear-gradient(135deg, #008000, #66cc66); /* grüner Verlauf */
	  color: white;
	  border-radius: 4px;
	  font-weight: bold;
	  box-shadow: inset 0 0 5px rgba(0,0,0,0.3);
	}
	.absent-vacation-abgelehnt {
	  background: linear-gradient(135deg, #cc0000, #ff3333); /* roter Verlauf */
	  color: white;
	  border-radius: 4px;
	  font-weight: bold;
	  box-shadow: inset 0 0 5px rgba(0,0,0,0.3);
	}
	.absent-vacation-unbezahlt {
	  background: linear-gradient(135deg, #cc6600, #ff9933); /* orangener Verlauf */
	  color: white;
	  border-radius: 4px;
	  font-weight: bold;
	  box-shadow: inset 0 0 5px rgba(0,0,0,0.3);
	}
	/* Wochenende bleibt unverändert */
	.weekend {
	  background-color: #f8d7da;
	  color: #721c24;
	}
        .hover-wrapper {
          position: relative;
          display: inline-block;
        }
        .hover-content {
          display: none;
          position: absolute;
          bottom: calc(100% + 6px);
          left: 50%;
          transform: translateX(-50%);
          background: rgba(0, 0, 0, 0.85);
          color: #fff;
          padding: 6px 10px;
          border-radius: 4px;
          white-space: nowrap;
          font-size: 12px;
          z-index: 50;
          box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }
        .hover-content::after {
          content: '';
          position: absolute;
          top: 100%;
          left: 50%;
          transform: translateX(-50%);
          border-width: 6px;
          border-style: solid;
          border-color: rgba(0, 0, 0, 0.85) transparent transparent transparent;
        }
        .hover-wrapper:hover .hover-content {
          display: block;
        }
    .month-navigation { margin: 20px auto; text-align: left; }
    .month-navigation a { padding: 10px 15px; font-size: 16px; background-color: #FFD700; color: #000; text-decoration: none; border-radius: 4px; margin: 0 5px; }
    .month-navigation a:hover { background-color: #FFC107; }
    .month-navigation span { font-size: 18px; font-weight: bold; margin-left: 10px; }
    .abwesenheit-editable { cursor: pointer; }
  </style>

    <main>
    <h1>Fahrer Abwesenheiten</h1>
    <button onclick="openModal('fahrerAbwesenheitModal')">Abwesenheit eintragen</button>
	<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
      <p style="color: green;">Abwesenheit erfolgreich eingetragen.</p>
    <?php endif; ?>
    
    <!-- Liste der beantragten Urlaube -->
    <section class="urlaub-antraege">
      <h2>Beantragte Urlaube</h2>
      <?php if (!empty($urlaubAntraege)): ?>
        <ul>
          <?php foreach ($urlaubAntraege as $antrag): ?>
            <li>
              <?= htmlspecialchars($antrag['nachname'] . ', ' . $antrag['vorname']) ?>:
              <?= date('d.m.y', strtotime($antrag['startdatum'])) ?> bis 
              <?= date('d.m.y', strtotime($antrag['enddatum'])) ?>
			  <?= htmlspecialchars($antrag['kommentar']) ?>:
              <button onclick="window.location.href='approve_urlaub.php?id=<?= $antrag['abwesenheit_id'] ?>'">Genehmigen</button>
              <button onclick="window.location.href='reject_urlaub.php?id=<?= $antrag['abwesenheit_id'] ?>'">Ablehnen</button>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>Keine beantragten Urlaube.</p>
      <?php endif; ?>
    </section>
    
    <!-- Monat wechseln -->
    <div class="month-navigation">
      <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&laquo; Vorheriger Monat</a>
      <span><?= ucfirst($formatter->format(strtotime($start_date))) ?></span>
      <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Nächster Monat &raquo;</a>
    </div>
    

    <table class="dashboard-table">
      <thead>
        <tr>
          <th>Fahrer</th>
          <?php foreach ($dates as $date): ?>
            <th class="<?= $date['isWeekend'] ? 'weekend' : '' ?>">
              <?= $date['day'] . '.'; ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($fahrer)): ?>
          <?php foreach ($fahrer as $person): ?>
            <tr>
            <td><a href="fahrer_bearbeiten.php?id=<?= urlencode($person['FahrerID']) ?>"><?= htmlspecialchars($person['nachname'] . ', ' . $person['vorname']) ?></a></td>
				<?php foreach ($dates as $date): ?>
					<?php
					// Initialisierung der Variablen für jede Zelle
                                        $cellClass = '';
                                        $cellText = '-';
                                        $hoverLines = [];
                                        $cellEntryData = null;

					// Abwesenheiten prüfen
					foreach ($abwesenheiten as $absence) {
						if ($absence['FahrerID'] == $person['FahrerID']
							&& $date['date'] >= $absence['startdatum']
							&& $date['date'] <= $absence['enddatum']) {

							// Farben und Texte basierend auf Abwesenheitsart, Grund und Status festlegen
							if ($absence['abwesenheitsart'] === 'Krankheit') {
								// Für Krankheiten: „K“ für krank, „KK“ für Kind krank
								if ($absence['grund'] === 'krank') {
									$cellClass = 'absent-krank';
									$cellText = 'K';
                                                                        if (!empty($absence['grund'])) {
                                                                                $hoverLines[] = $absence['grund'];
                                                                        }
                                                                } elseif ($absence['grund'] === 'Kind krank') {
                                                                        $cellClass = 'absent-kind-krank';
                                                                        $cellText = 'KK';
                                                                        if (!empty($absence['grund'])) {
                                                                                $hoverLines[] = $absence['grund'];
                                                                        }
                                                                }
                                                        } elseif ($absence['abwesenheitsart'] === 'Urlaub') {
								// Für Urlaub: Statusabhängige Darstellung
                                                                        if ($absence['status'] === 'beantragt') {
                                                                                $cellClass = 'absent-vacation-beantragt';
                                                                                $cellText = 'Ub';
                                                                                if (!empty($absence['grund'])) {
                                                                                        $hoverLines[] = $absence['grund'];
                                                                                }
                                                                        } elseif ($absence['status'] === 'genehmigt') {
                                                                                if ($absence['grund'] === 'unbezahlter Urlaub') {
                                                                                        $cellClass = 'absent-vacation-unbezahlt';
                                                                                        $cellText = 'UU';
                                                                                        if (!empty($absence['grund'])) {
                                                                                                $hoverLines[] = $absence['grund'];
                                                                                        }
                                                                                } else {
                                                                                        $cellClass = 'absent-vacation-genehmigt';
                                                                                        $cellText = 'U';
                                                                                        if (!empty($absence['grund'])) {
                                                                                                $hoverLines[] = $absence['grund'];
                                                                                        }
                                                                                }
                                                                        } elseif ($absence['status'] === 'abgelehnt') {
                                                                                $cellClass = 'absent-vacation-abgelehnt';
                                                                                $cellText = 'Ua';
                                                                                if (!empty($absence['grund'])) {
                                                                                        $hoverLines[] = $absence['grund'];
                                                                                }
                                                                        }
                                                        }

                                                        if (!empty($absence['kommentar'])) {
                                                                $hoverLines[] = 'Kommentar: ' . $absence['kommentar'];
                                                        }

                                                        $cellEntryData = [
                                                                'id' => (int)$absence['id'],
                                                                'fahrer_id' => (int)$absence['FahrerID'],
                                                                'fahrer_name' => $person['nachname'] . ', ' . $person['vorname'],
                                                                'abwesenheitsart' => $absence['abwesenheitsart'],
                                                                'grund' => $absence['grund'],
                                                                'status' => $absence['status'],
                                                                'startdatum' => $absence['startdatum'],
                                                                'enddatum' => $absence['enddatum'],
                                                                'kommentar' => $absence['kommentar'],
                                                        ];
                                                        break; // Schleife beenden, sobald eine passende Abwesenheit gefunden wurde
                                                }
                                        }
                                        $cellClasses = trim($cellClass . (!empty($hoverLines) ? ' has-hover' : '') . ($cellEntryData ? ' abwesenheit-editable' : ''));
                                        $dataEntry = $cellEntryData ? ' data-entry="' . htmlspecialchars(json_encode($cellEntryData), ENT_QUOTES, 'UTF-8') . '"' : '';
                                        ?>
                                        <td class="<?= $cellClasses ?>"<?= $dataEntry ?>>
                                                <div class="hover-wrapper">
                                                        <span class="hover-label"><?= htmlspecialchars($cellText); ?></span>
                                                        <?php if (!empty($hoverLines)): ?>
                                                                <div class="hover-content">
                                                                        <?php foreach ($hoverLines as $line): ?>
                                                                                <div><?= htmlspecialchars($line); ?></div>
                                                                        <?php endforeach; ?>
                                                                </div>
                                                        <?php endif; ?>
                                                </div>
                                        </td>
				<?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="<?= count($dates) + 1 ?>">Keine Fahrer gefunden.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <!-- Modal für Abwesenheitseintrag -->
  <?php include 'modals/add_fahrer_abwesenheit_modal.php'; ?>

  <!-- Modal für Abwesenheitsbearbeitung -->
  <div id="editFahrerAbwesenheitModal" class="modal" style="display:none;">
    <div class="modal-content">
      <span onclick="closeEditFahrerAbwesenheitModal()" class="close">&times;</span>
      <h2>Abwesenheit bearbeiten</h2>
      <form action="fahrer_abwesenheit_bearbeiten.php" method="POST">
        <input type="hidden" name="abwesenheit_id" id="edit_abwesenheit_id">

        <p><strong>Fahrer:</strong> <span id="edit_fahrer_name"></span></p>

        <label for="edit_abwesenheitsart">Abwesenheitsart:</label>
        <select name="abwesenheitsart" id="edit_abwesenheitsart" required onchange="updateEditGrundOptions()">
          <option value="Krankheit">Krankheit</option>
          <option value="Urlaub">Urlaub</option>
        </select>

        <label for="edit_grund">Grund:</label>
        <select name="grund" id="edit_grund" required></select>

        <label for="edit_status">Status:</label>
        <select name="status" id="edit_status">
          <option value="">-</option>
          <option value="beantragt">beantragt</option>
          <option value="genehmigt">genehmigt</option>
          <option value="abgelehnt">abgelehnt</option>
        </select>

        <label for="edit_startdatum">Startdatum:</label>
        <input type="date" name="startdatum" id="edit_startdatum" required>

        <label for="edit_enddatum">Enddatum:</label>
        <input type="date" name="enddatum" id="edit_enddatum" required>

        <label for="edit_kommentar">Kommentar:</label>
        <textarea name="kommentar" id="edit_kommentar"></textarea>

        <button type="submit" name="action" value="update">Speichern</button>
        <button type="submit" name="action" value="delete" onclick="return confirm('Diesen Eintrag wirklich löschen?');">Löschen</button>
      </form>
    </div>
  </div>

  <script>
    const editGrundOptionen = {
      Krankheit: ['krank', 'Kind krank'],
      Urlaub: ['Urlaub', 'unbezahlter Urlaub']
    };

    function updateEditGrundOptions(selectedValue = null) {
      const art = document.getElementById('edit_abwesenheitsart').value;
      const grundSelect = document.getElementById('edit_grund');
      const optionen = editGrundOptionen[art] || [];

      grundSelect.innerHTML = '';
      optionen.forEach((wert) => {
        const option = document.createElement('option');
        option.value = wert;
        option.textContent = wert;
        grundSelect.appendChild(option);
      });

      if (selectedValue && optionen.includes(selectedValue)) {
        grundSelect.value = selectedValue;
      }
    }

    function openEditFahrerAbwesenheitModal(entry) {
      if (!entry || !entry.id) {
        return;
      }

      document.getElementById('edit_abwesenheit_id').value = entry.id;
      document.getElementById('edit_fahrer_name').textContent = entry.fahrer_name || '';
      document.getElementById('edit_abwesenheitsart').value = entry.abwesenheitsart || 'Krankheit';
      updateEditGrundOptions(entry.grund || null);
      document.getElementById('edit_status').value = entry.status || '';
      document.getElementById('edit_startdatum').value = entry.startdatum || '';
      document.getElementById('edit_enddatum').value = entry.enddatum || '';
      document.getElementById('edit_kommentar').value = entry.kommentar || '';
      document.getElementById('editFahrerAbwesenheitModal').style.display = 'block';
    }

    function closeEditFahrerAbwesenheitModal() {
      document.getElementById('editFahrerAbwesenheitModal').style.display = 'none';
    }

    document.querySelectorAll('[data-entry]').forEach((cell) => {
      cell.addEventListener('dblclick', () => {
        const payload = cell.getAttribute('data-entry');
        if (!payload) {
          return;
        }

        try {
          const entry = JSON.parse(payload);
          openEditFahrerAbwesenheitModal(entry);
        } catch (error) {
          console.error('Ungültige Abwesenheitsdaten', error);
        }
      });
    });

    document.querySelector('.burger-menu').addEventListener('click', () => {
      document.querySelector('.nav-links').classList.toggle('active');
    });
  </script>

</body>
</html>
