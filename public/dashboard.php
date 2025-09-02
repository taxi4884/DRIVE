<?php
// Include authentication and database connection
require_once '../includes/bootstrap.php';

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
$end_date = date('Y-m-d', strtotime('+6 days'));

// Mitarbeiter abrufen
$stmt = $pdo->prepare("SELECT vorname, nachname, mitarbeiter_id FROM mitarbeiter_zentrale ORDER BY nachname ASC");
$stmt->execute();
$mitarbeiter = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Wochentage für den Zeitraum vorbereiten
$dates = [];
for ($date = strtotime($start_date); $date <= strtotime($end_date); $date = strtotime('+1 day', $date)) {
    $dates[] = [
        'day' => date('d.m.', $date),
        'isWeekend' => in_array(date('N', $date), [6, 7]), // Samstag oder Sonntag
        'date' => date('Y-m-d', $date),
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
?>
<?php
$title = 'Dashboard';
include '../includes/layout.php';
?>
    <div class="container">
        <header>
			<h1 id="greeting"></h1>
			<p id="subtitle"><?php echo $subtitle_message; ?></p>
		</header>


        <main>
		
			<?php
				try {
					$stmt = $pdo->prepare("
						SELECT 
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
						ORDER BY firmenname, sfa.anmeldung DESC
					");
					$stmt->execute();
					$aktive = $stmt->fetchAll(PDO::FETCH_ASSOC);

					$firmenGruppiert = [];

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
						$firmenGruppiert[$anzeigeName][] = $eintrag;
					}

                                        if (!empty($firmenGruppiert)) {
                                                foreach ($firmenGruppiert as $firma => $fahrerListe) {
                                                        echo "<section class='widget card mb-3'><div class='card-body'>";
                                                        echo "<h2>" . htmlspecialchars($firma) . "</h2>";
                                                        echo "<ul>";
                                                        foreach ($fahrerListe as $fahrer) {
                                                                echo "<li><strong>" . htmlspecialchars($fahrer['fahrer_vorname']) . " " . htmlspecialchars($fahrer['fahrer_nachname']) . "</strong>";
                                                                echo " – Kennzeichen: " . htmlspecialchars($fahrer['kennzeichen']);
                                                                echo "</li>";
                                                        }
                                                        echo "</ul>";
                                                        echo "</div></section>";
                                                }
                                        } else {
                                                echo "<section class='widget card mb-3'><div class='card-body'><p>Aktuell sind keine Fahrer angemeldet.</p></div></section>";
                                        }
                                } catch (PDOException $e) {
                                        echo "<section class='widget card mb-3'><div class='card-body'><p>Fehler bei der Fahrerabfrage: " . htmlspecialchars($e->getMessage()) . "</p></div></section>";
                                }
                                ?>

            <section id="tageshighlights" class="widget card mb-3">
                <div class="card-body">
                <h2>Tageshighlights</h2>
                <div id="birthdays">
                    <h3>Geburtstage</h3>
                    <ul>
                        <?php
                        try {
                            // Fahrer-Geburtstage
                            $stmt_fahrer = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS name FROM Fahrer WHERE DATE_FORMAT(geburtsdatum, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')");
                            $stmt_fahrer->execute();
                            $fahrer_birthdays = $stmt_fahrer->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $fahrer_birthdays = [];
                        }

                        try {
                            // Mitarbeiter-Zentrale-Geburtstage
                            $stmt_zentrale = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS name FROM mitarbeiter_zentrale WHERE DATE_FORMAT(geburtsdatum, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')");
                            $stmt_zentrale->execute();
                            $zentrale_birthdays = $stmt_zentrale->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $zentrale_birthdays = [];
                        }

                        // Zusammenführen der Ergebnisse
                        $birthdays = array_merge($fahrer_birthdays, $zentrale_birthdays);

                        if ($birthdays) {
                            foreach ($birthdays as $person) {
                                echo "<li>" . htmlspecialchars($person['name']) . "</li>";
                            }
                        } else {
                            echo "<li>Keine Geburtstage heute.</li>";
                        }
                        ?>
                    </ul>
                </div>
                <div id="fällige-termine">
                    <h3>Fällige Wartungen</h3>
                    <ul>
                        <?php
                        try {
                            $stmt = $pdo->prepare(
                                "SELECT DATE_FORMAT(w.Wartungsdatum, '%d.%m.%Y') AS Wartungsdatum, f.Konzessionsnummer, f.Kennzeichen, f.Marke, f.Modell 
                                 FROM Wartung w 
                                 JOIN Fahrzeuge f ON w.FahrzeugID = f.FahrzeugID 
                                 WHERE DATE(w.Wartungsdatum) = CURDATE()"
                            );
                            $stmt->execute();
                            $wartungen = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if ($wartungen) {
                                foreach ($wartungen as $wartung) {
                                    echo "<li>" . htmlspecialchars($wartung['Wartungsdatum']) . ": " . htmlspecialchars($wartung['Marke']) . " " . htmlspecialchars($wartung['Modell']) . " (" . htmlspecialchars($wartung['Kennzeichen']) . ")</li>";
                                }
                            } else {
                                echo "<li>Keine fälligen Wartungen heute.</li>";
                            }
                        } catch (PDOException $e) {
                            echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
                        }
                        ?>
                    </ul>
                </div>
                </div>
            </section>

            <section id="krank-urlaub" class="widget card mb-3">
                <div class="card-body">
                <h2>Krank/Urlaub</h2>
                <div id="abteilungen">
                    <div class='department'>
                        <h3>Fahrer</h3>
                        <ul>
                            <?php
                            try {
                                $stmt_fahrer_absences = $pdo->prepare(
                                    "SELECT CONCAT(f.Vorname, ' ', f.Nachname) AS name, a.abwesenheitsart, DATE_FORMAT(a.startdatum, '%d.%m.%Y') AS startdatum, DATE_FORMAT(a.enddatum, '%d.%m.%Y') AS enddatum 
                                     FROM FahrerAbwesenheiten a 
                                     JOIN Fahrer f ON a.FahrerID = f.FahrerID 
                                     WHERE CURDATE() BETWEEN a.startdatum AND a.enddatum"
                                );
                                $stmt_fahrer_absences->execute();
                                $fahrer_absences = $stmt_fahrer_absences->fetchAll(PDO::FETCH_ASSOC);

                                if ($fahrer_absences) {
                                    foreach ($fahrer_absences as $absence) {
                                        echo "<li>" . htmlspecialchars($absence['name']) . " (" . htmlspecialchars($absence['abwesenheitsart']) . ", " . htmlspecialchars($absence['startdatum']) . " bis " . htmlspecialchars($absence['enddatum']) . ")</li>";
                                    }
                                } else {
                                    echo "<li>Keine Abwesenheiten</li>";
                                }
                            } catch (PDOException $e) {
                                echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
                            }
                            ?>
                        </ul>
                    </div>

                    <div class='department'>
                        <h3>Zentrale</h3>
                        <ul>
                            <?php
                            try {
                                $stmt_zentrale_absences = $pdo->prepare(
                                    "SELECT CONCAT(mz.Vorname, ' ', mz.Nachname) AS name, az.typ, DATE_FORMAT(az.startdatum, '%d.%m.%Y') AS startdatum, DATE_FORMAT(az.enddatum, '%d.%m.%Y') AS enddatum 
                                     FROM abwesenheiten_zentrale az 
                                     JOIN mitarbeiter_zentrale mz ON az.mitarbeiter_id = mz.mitarbeiter_id 
                                     WHERE CURDATE() BETWEEN az.startdatum AND az.enddatum"
                                );
                                $stmt_zentrale_absences->execute();
                                $zentrale_absences = $stmt_zentrale_absences->fetchAll(PDO::FETCH_ASSOC);

                                if ($zentrale_absences) {
                                    foreach ($zentrale_absences as $absence) {
                                        echo "<li>" . htmlspecialchars($absence['name']) . " (" . htmlspecialchars($absence['typ']) . ", " . htmlspecialchars($absence['startdatum']) . " bis " . htmlspecialchars($absence['enddatum']) . ")</li>";
                                    }
                                } else {
                                    echo "<li>Keine Abwesenheiten</li>";
                                }
                            } catch (PDOException $e) {
                                echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
                            }
                            ?>
                        </ul>
                    </div>
                </div>
                </div>
            </section>

            <section id="tuev" class="widget card mb-3">
                <div class="card-body">
                <h2>Fällige TÜV</h2>
                <ul>
                    <?php
                    try {
                        $stmt = $pdo->prepare("SELECT Konzessionsnummer, Marke, Modell, DATE_FORMAT(HU, '%d.%m.%Y') AS HU FROM Fahrzeuge WHERE HU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) ORDER BY HU ASC");
                        $stmt->execute();
                        $fahrzeuge_hu = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($fahrzeuge_hu) {
                            foreach ($fahrzeuge_hu as $fahrzeug) {
                                echo "<li>" . htmlspecialchars($fahrzeug['Konzessionsnummer']) . " - " . htmlspecialchars($fahrzeug['Marke']) . " " . htmlspecialchars($fahrzeug['Modell']) . ": " . htmlspecialchars($fahrzeug['HU']) . "</li>";
                            }
                        } else {
                            echo "<li>Keine fälligen TÜV.</li>";
                        }
                    } catch (PDOException $e) {
                        echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
                    }
                    ?>
                </ul>
                </div>
            </section>

            <section id="eichung" class="widget card mb-3">
                <div class="card-body">
                <h2>Wartungstermine</h2>
                <ul>
                    <?php
                    try {
                        $stmt = $pdo->prepare("
						    SELECT DATE_FORMAT(w.Wartungsdatum, '%d.%m.%Y') AS FormattedDate, w.Werkstatt, f.Konzessionsnummer, f.Marke, f.Modell FROM Wartung w JOIN Fahrzeuge f ON w.FahrzeugID = f.FahrzeugID WHERE w.Wartungsdatum BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) ORDER BY w.Wartungsdatum ASC");
                        $stmt->execute();
                        $fahrzeuge_eichung = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($fahrzeuge_eichung) {
                            foreach ($fahrzeuge_eichung as $fahrzeug) {
                                echo "<li>" . htmlspecialchars($fahrzeug['Konzessionsnummer']) . " - " . htmlspecialchars($fahrzeug['Marke']) . ": " . htmlspecialchars($fahrzeug['FormattedDate']) . "</li>";
                            }
                        } else {
                            echo "<li>Keine fälligen Eichungen.</li>";
                        }
                    } catch (PDOException $e) {
                        echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
                    }
                    ?>
                </ul>
                </div>
            </section>

            <section id="pschein" class="widget card mb-3">
                                <div class="card-body">
                                <h2>Bald ablaufende P-Scheine</h2>
                                <ul>
					<?php
					try {
						$stmt = $pdo->prepare("SELECT CONCAT(Vorname, ' ', Nachname) AS Name, DATE_FORMAT(PScheinGueltigkeit, '%d.%m.%Y') AS PScheinGueltigkeit FROM Fahrer WHERE PScheinGueltigkeit BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH) ORDER BY PScheinGueltigkeit ASC");
						$stmt->execute();
						$fahrer_pschein = $stmt->fetchAll(PDO::FETCH_ASSOC);

						if ($fahrer_pschein) {
							foreach ($fahrer_pschein as $fahrer) {
								echo "<li>" . htmlspecialchars($fahrer['Name']) . " - Gültig bis: " . htmlspecialchars($fahrer['PScheinGueltigkeit']) . "</li>";
							}
						} else {
							echo "<li>Alle P-Scheine aktuell.</li>";
						}
					} catch (PDOException $e) {
						echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
					}
					?>
                                </ul>
                                </div>
                        </section>
			
                        <section id="zentraler-dienstplan" class="widget card mb-3">
                                <div class="card-body">
                                <h2>Zentraler Dienstplan</h2>
                                <div id="heutige-schicht">
					<h3>Heutige Schicht</h3>
					<ul>
						<?php
						try {
							$stmt_today = $pdo->prepare("
								SELECT CONCAT(mz.vorname, ' ', mz.nachname) AS name, s.name AS schicht
								FROM dienstplan d
								JOIN mitarbeiter_zentrale mz ON d.mitarbeiter_id = mz.mitarbeiter_id
								JOIN schichten s ON d.schicht_id = s.schicht_id
								WHERE DATE(d.datum) = CURDATE()
							");
							$stmt_today->execute();
							$heutige_schicht = $stmt_today->fetchAll(PDO::FETCH_ASSOC);

							if ($heutige_schicht) {
								foreach ($heutige_schicht as $schicht) {
									echo "<li>" . htmlspecialchars($schicht['name']) . ": " . htmlspecialchars($schicht['schicht']) . "</li>";
								}
							} else {
								echo "<li>Keine Schichtdaten für heute verfügbar.</li>";
							}
						} catch (PDOException $e) {
							echo "<li>Fehler bei der Abfrage: " . htmlspecialchars($e->getMessage()) . "</li>";
						}
						?>
					</ul>
                                </div>
                                </div>
                        </section>

            <section id="dienstplan-zentrale" class="widget card mb-3">
                <div class="card-body">
                <h2>Schichtplan Zentrale</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mitarbeiter</th>
                            <?php foreach ($dates as $date): ?>
                                <th class="<?= $date['isWeekend'] ? 'weekend' : ''; ?>">
                                    <?= htmlspecialchars($date['day']); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($mitarbeiter)): ?>
                            <?php foreach ($mitarbeiter as $person): ?>
                                <tr>
                                    <td><?= htmlspecialchars($person['nachname'] . ', ' . $person['vorname']); ?></td>
                                    <?php foreach ($dates as $date): ?>
                                        <?php
                                        $cellClass = '';
                                        $cellText = '-';

                                        // Schicht aus Dienstplan abrufen
                                        if (isset($dienstplanMap[$person['mitarbeiter_id']][$date['date']])) {
                                            $schicht = $dienstplanMap[$person['mitarbeiter_id']][$date['date']];
                                            $cellText = $schicht;
                                            switch ($schicht) {
                                                case 'F0': case 'F1': case 'F3':
                                                    $cellClass = 'early-shift';
                                                    break;
                                                case 'F2':
                                                    $cellClass = 'mid-shift';
                                                    break;
                                                case 'S0': case 'S1':
                                                    $cellClass = 'late-shift';
                                                    break;
                                                case 'N':
                                                    $cellClass = 'night-shift';
                                                    break;
                                            }
                                        }
                                        ?>
                                        <td class="<?= $cellClass; ?>">
                                            <?= htmlspecialchars($cellText); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count($dates) + 1; ?>">Keine Mitarbeiter gefunden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
        document.querySelector('.burger-menu').addEventListener('click', () => {
            document.querySelector('.nav-links').classList.toggle('active');
        });
    </script>
</body>
</html>
