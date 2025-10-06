<?php
// schulungsverwaltung.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/bootstrap.php';
require_once __DIR__ . '/versand.php';

function checkUndVersendeEinladungen($termin, $maxEinladungen = 8){
    global $pdo;

    // Ziel: 5 neue + 3 alte
    $zielNeu = 5;
    $zielAlt = $maxEinladungen - $zielNeu;

    // Bereits bestätigte Teilnehmer zählen
    $stmt = $pdo->prepare("SELECT rueckmeldung_status, nicht_bestanden_count FROM schulungsteilnehmer WHERE schulungstermin = :termin");
    $stmt->execute([':termin' => $termin]);
    $bestehende = $stmt->fetchAll();

    $bereitsNeu = 0;
    $bereitsAlt = 0;
    foreach ($bestehende as $teilnehmer) {
        if ((int)$teilnehmer['rueckmeldung_status'] === 1) {
            if ((int)$teilnehmer['nicht_bestanden_count'] > 0) {
                $bereitsAlt++;
            } else {
                $bereitsNeu++;
            }
        }
    }

    // Verfügbare Plätze
    $freiNeu = max(0, $zielNeu - $bereitsNeu);
    $freiAlt = max(0, $zielAlt - $bereitsAlt);
    $gesamtFrei = max(0, $maxEinladungen - $bereitsNeu - $bereitsAlt);

    // Wenn nicht genug Neue da, mit Alten auffüllen
    if ($freiNeu < $zielNeu) {
        $freiAlt = min($zielAlt + ($zielNeu - $freiNeu), $gesamtFrei);
    }

	// -------------------------------------------
	// Neue einladen
	// -------------------------------------------
	if ($freiNeu > 0 && $gesamtFrei > 0) {
		$stmt = $pdo->prepare(
			"SELECT id
			   FROM schulungsteilnehmer
			  WHERE schulungstermin = :termin          -- Termin ist schon gesetzt
				AND letzte_einladung IS NULL          -- noch keine Mail verschickt
				AND nicht_bestanden_count = 0         -- neue TN
			  ORDER BY erstellt_am ASC
			  LIMIT :limit"
		);
		$stmt->bindValue(':termin', $termin, PDO::PARAM_STR);   // explizit als String
		$stmt->bindValue(':limit',  $freiNeu, PDO::PARAM_INT);
		$stmt->execute();
		$neue = $stmt->fetchAll(PDO::FETCH_COLUMN);

		foreach ($neue as $id) {
			versendeEinladung($id, $termin);
			if (--$gesamtFrei <= 0) break;
		}
	}

	// -------------------------------------------
	// Wiederholer einladen
	// -------------------------------------------
	if ($freiAlt > 0 && $gesamtFrei > 0) {
		$stmt = $pdo->prepare(
			"SELECT id
			   FROM schulungsteilnehmer
			  WHERE schulungstermin = :termin
				AND letzte_einladung IS NULL
				AND nicht_bestanden_count > 0         -- Wiederholer
			  ORDER BY erstellt_am ASC
			  LIMIT :limit"
		);
		$stmt->bindValue(':termin', $termin);
		$stmt->bindValue(':limit',  $freiAlt, PDO::PARAM_INT);
		$stmt->execute();
		$alte = $stmt->fetchAll(PDO::FETCH_COLUMN);

		foreach ($alte as $id) {
			versendeEinladung($id, $termin);
			if (--$gesamtFrei <= 0) break;
		}
	}
}
function versendeEinladung($id, $termin){
    global $pdo;

    /* Teilnehmerdaten holen */
    $stmt = $pdo->prepare(
        "SELECT vorname, email
           FROM schulungsteilnehmer
          WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
    $teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teilnehmer) {
        return false;                       // ID nicht gefunden
    }

    $vorname         = $teilnehmer['vorname'];
    $email           = $teilnehmer['email'];
    $praxistagdatum  = DateTime::createFromFormat('Y-m-d', $termin)
                        ->format('d.m.y');

    /* E‑Mail verschicken (versand.php) */
    if (sendInvitation($id, $vorname, $email, $praxistagdatum)) {

        /* Nur noch den Einladungs‑Stempel setzen */
        $update = $pdo->prepare(
            "UPDATE schulungsteilnehmer
                SET letzte_einladung = CURDATE()
              WHERE id = :id"
        );
        $update->execute([':id' => $id]);

        return true;
    }

    return false;                           // Versand fehlgeschlagen
}


// Teilnehmer automatisch freigeben, deren Sperrfrist abgelaufen ist
$freigabeQuery = "
    UPDATE schulungsteilnehmer 
    SET gesperrt_bis = NULL, nicht_bestanden_count = 0 
    WHERE gesperrt_bis IS NOT NULL AND gesperrt_bis <= CURDATE()
";
$pdo->exec($freigabeQuery);

// Verwaltungsberechtigung prüfen
$berechtigt = false;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT Schulungsverwaltung FROM Benutzer WHERE BenutzerID = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $berechtigt = $stmt->fetchColumn() == 1;
}

// Teilnehmer aus der Datenbank abrufen
$query = "SELECT id, vorname, nachname, 
                 DATE_FORMAT(erstellt_am, '%d.%m.%Y') AS erstellt_am, 
                 COALESCE(schulungstermin, '') AS schulungstermin, 
                 DATE_FORMAT(letzte_einladung, '%d.%m.%Y') AS letzte_einladung,
                 rueckmeldung_status,
                 unternehmer,
                 gesperrt_bis,
				 nicht_bestanden_count,
				 abschlusstest_bestanden,
			     abschluss_prozent,
			     letzter_themen_id
          FROM schulungsteilnehmer 
          ORDER BY STR_TO_DATE(erstellt_am, '%Y-%m-%d') DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$teilnehmer = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Termin speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['termin_speichern'])) {
    if (isset($_POST['id'])) {
        // Einzeln: Nur für diesen Teilnehmer speichern, kein Versand
        $id = (int)$_POST['id'];
        $termin = $_POST['schulungstermin'];
        try {
            $updateQuery = "UPDATE schulungsteilnehmer SET schulungstermin = :termin WHERE id = :id";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([
                ':termin' => $termin,
                ':id' => $id
            ]);
            $message = "Schulungstermin erfolgreich gespeichert.";
        } catch (PDOException $e) {
            $message = "Fehler beim Speichern des Termins: " . $e->getMessage();
        }

    } elseif (isset($_POST['global_schulungstermin'])) {
        // Global: Alle aktualisieren + Massenversand
        $termin = $_POST['global_schulungstermin'];
        try {
            $updateQuery = "UPDATE schulungsteilnehmer SET schulungstermin = :termin";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([':termin' => $termin]);
            $message = "Globaler Schulungstermin erfolgreich gesetzt.";

            // Nur hier: Massenversand
            checkUndVersendeEinladungen($termin);

        } catch (PDOException $e) {
            $message = "Fehler beim Setzen des globalen Termins: " . $e->getMessage();
        }
    }

    // Weiterleitung nach dem Speichern
    header("Location: schulungsverwaltung.php");
    exit();
}

// Teilnehmer löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submit'])) {
    $delete_id = (int)$_POST['delete_id'];
    try {
        $deleteQuery = "DELETE FROM schulungsteilnehmer WHERE id = :id";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([':id' => $delete_id]);
        $_SESSION['message'] = "Teilnehmer erfolgreich gelöscht.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Löschen des Teilnehmers: " . $e->getMessage();
    }
    header("Location: schulungsverwaltung.php");
    exit();
}

// Rückmeldungen zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rueckmeldung_zuruecksetzen'])) {
    try {
        $resetQuery = "UPDATE schulungsteilnehmer SET rueckmeldung_status = NULL";
        $resetStmt = $pdo->prepare($resetQuery);
        $resetStmt->execute();
        $_SESSION['message'] = "Alle Rückmeldungen wurden erfolgreich zurückgesetzt.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Zurücksetzen der Rückmeldungen: " . $e->getMessage();
    }
    header("Location: schulungsverwaltung.php");
    exit();
}

// Statistik auslesen
$statsQuery = "
    SELECT 
        COUNT(*) AS gesamt,
        SUM(bestanden_status = 1) AS bestanden,
        SUM(bestanden_status = 0) AS nicht_bestanden,
        SUM(bestanden_status IS NULL) AS offen
    FROM schulungsteilnehmer
";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Schulungsergebnis speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_bestanden'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status']; // 1 = bestanden, 0 = nicht bestanden

    try {
        if ($status === 1) {
            // Teilnehmer hat bestanden → löschen
            $stmt = $pdo->prepare("DELETE FROM schulungsteilnehmer WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['message'] = "Teilnehmer hat bestanden und wurde gelöscht.";
        } else {
            // Teilnehmer hat nicht bestanden → Count ermitteln und erhöhen
            $stmt = $pdo->prepare("SELECT nicht_bestanden_count FROM schulungsteilnehmer WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $count = (int)$stmt->fetchColumn();

            $count++; // um eins erhöhen

            // Sperrfrist berechnen: 3x nicht bestanden → 90 Tage, sonst 14 Tage
            $sperrfristTage = $count >= 3 ? 90 : 14;
            $sperrBis = (new DateTime())->modify("+$sperrfristTage days")->format('Y-m-d');

            // Aktualisieren: Status, Zähler und Sperrdatum
            $updateStmt = $pdo->prepare("
                UPDATE schulungsteilnehmer 
                SET bestanden_status = 0,
                    nicht_bestanden_count = :count,
                    gesperrt_bis = :sperr_bis 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':count' => $count,
                ':sperr_bis' => $sperrBis,
                ':id' => $id
            ]);

            $_SESSION['message'] = "Teilnehmer wurde auf 'nicht bestanden' gesetzt und für $sperrfristTage Tage gesperrt.";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Speichern des Ergebnisses: " . $e->getMessage();
    }

    header("Location: schulungsverwaltung.php");
    exit();
}

// Entsperrung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entsperren'])) {
    $id = (int)$_POST['id'];
    try {
        $entsperrQuery = "UPDATE schulungsteilnehmer 
                          SET gesperrt_bis = NULL, nicht_bestanden_count = 0 
                          WHERE id = :id";
        $entsperrStmt = $pdo->prepare($entsperrQuery);
        $entsperrStmt->execute([':id' => $id]);
        $_SESSION['message'] = "Teilnehmer wurde erfolgreich entsperrt.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Entsperren: " . $e->getMessage();
    }
    header("Location: schulungsverwaltung.php");
    exit();
}
/* nächstes Datum, zu dem schon Einladungen rausgingen */
$nextQuery = "
    SELECT MIN(schulungstermin)
      FROM schulungsteilnehmer
     WHERE schulungstermin >= CURDATE()
       AND letzte_einladung = schulungstermin   -- ⇦ NEU
";
$nextTermin = $pdo->query($nextQuery)->fetchColumn();


/* 2. Rückmeldungen für diesen Termin zählen */
$counts = ['zugesagt'=>0, 'abgesagt'=>0, 'offen'=>0];

if ($nextTermin) {
    $countQuery = "
        SELECT
            SUM(rueckmeldung_status = 1)        AS zugesagt,
            SUM(rueckmeldung_status = 0)        AS abgesagt,
            SUM(rueckmeldung_status IS NULL)    AS offen
        FROM schulungsteilnehmer
        WHERE schulungstermin   = :termin
          AND letzte_einladung  = :termin      -- ⇦ NEU
    ";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute([':termin' => $nextTermin]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>
<?php
$title = 'Schulungsverwaltung';
include __DIR__ . '/../includes/layout.php';
?>
    <!-- Bootstrap CSS für einheitliches Styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

        <main>
		<div class="alert alert-info text-center">
		<strong>Teilnehmer gesamt:</strong> <?php echo $stats['gesamt']; ?> |
		<strong>Bestanden:</strong> <?php echo $stats['bestanden']; ?> |
		<strong>Nicht bestanden:</strong> <?php echo $stats['nicht_bestanden']; ?> |
		<strong>Offen:</strong> <?php echo $stats['offen']; ?>
	</div>
	<?php if ($nextTermin): ?>
		<?php
			$dateDe = (new DateTime($nextTermin))->format('d.m.y');
		?>
		<div class="alert alert-warning d-flex justify-content-center gap-4 align-items-center">
			<strong>Nächster Praxistag: <?= $dateDe ?></strong>

			<span class="badge bg-success">
				✔ Zugesagt: <?= $counts['zugesagt'] ?>
			</span>
			<span class="badge bg-danger">
				❌ Abgesagt: <?= $counts['abgesagt'] ?>
			</span>
			<span class="badge bg-secondary">
				❓ Offen: <?= $counts['offen'] ?>
			</span>
		</div>
	<?php else: ?>
		<div class="alert alert-info text-center">
			Noch kein zukünftiger Termin geplant.
		</div>
	<?php endif; ?>


        <h1>Schulungsverwaltung</h1>
		
			<!-- Nachricht aus der Session anzeigen -->
			<?php if (isset($_SESSION['message'])): ?>
				<div class="alert alert-success text-center">
					<?php echo $_SESSION['message']; ?>
				</div>
				<?php unset($_SESSION['message']); ?>
			<?php endif; ?>
		
		<?php if ($berechtigt): ?>
			<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
				<!-- Globaler Schulungstermin setzen -->
				<form method="POST" class="d-flex align-items-center gap-2 mb-0">
					<label for="global_schulungstermin" class="form-label mb-0">Globaler Schulungstermin:</label>
					<input type="date" id="global_schulungstermin" name="global_schulungstermin" class="form-control" required>
					<button type="submit" name="termin_speichern" class="btn btn-warning" style="width:100%;">Global setzen</button>
				</form>

				<!-- Rückmeldungen zurücksetzen -->
				<form method="POST" class="mb-0">
					<button type="submit" name="rueckmeldung_zuruecksetzen" class="btn btn-danger">
						Rückmeldungen zurücksetzen
					</button>
				</form>

				<!-- Alle PDFs -->
				<form method="GET" action="pdf_alle_bestaetigt.php" class="mb-0">
					<button type="submit" class="btn btn-success">
						<i class="fas fa-file-pdf"></i> Alle PDF für bestätigte Teilnehmer
					</button>
				</form>
				
				<form action="/schulung/abfrage_status_schulung.php" method="post" class="mb-4">
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-sync-alt"></i> Status aus Funkschulung abrufen
					</button>
				</form>
			</div>
		 <?php endif; ?>

        <!-- Tabelle mit Teilnehmern -->
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
					<th>Status</th>
                    <th>Vorname</th>
                    <th>Nachname</th>
                    <th>Angemeldet am</th>
                    <th>Rückmeldung</th>
                    <th>Nächster Schulungstermin</th>
					<th>Letzte Einladung</th>
					<th>Unternehmer</th>
					<th>Funkschulung</th>
					<?php if ($berechtigt): ?>
						<th>Schulungsergebnis</th>
						<th>Aktionen</th>
						<th>Verwaltung</th>
					<?php else: ?>
						<th>Sperrstatus</th>
					<?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teilnehmer as $row): ?>
                    <tr>
						<td>
							<?php if (!empty($row['gesperrt_bis']) && new DateTime($row['gesperrt_bis']) > new DateTime()): ?>
								<i class="fas fa-ban text-danger" title="Gesperrt bis <?= date('d.m.y', strtotime($row['gesperrt_bis'])) ?>"></i>
							<?php elseif ((int)$row['nicht_bestanden_count'] > 0): ?>
								<i class="fas fa-rotate-left text-warning" title="Wiederholer (bereits durchgefallen)"></i>
							<?php else: ?>
								<i class="fas fa-user-plus text-success" title="Neuer Teilnehmer"></i>
							<?php endif; ?>
						</td>
                        <td><?php echo htmlspecialchars($row['vorname']); ?></td>
                        <td><?php echo htmlspecialchars($row['nachname']); ?></td>
                        <td><?php echo htmlspecialchars($row['erstellt_am']); ?></td>
                        <td>
                            <?php 
                            if ($row['rueckmeldung_status'] === null) {
                                echo '<i class="fas fa-question-circle text-secondary"></i> Keine Rückmeldung';
                            } elseif ((int)$row['rueckmeldung_status'] === 1) {
                                echo '<i class="fas fa-check-circle text-success"></i> Teilnahme bestätigt';
                            } elseif ((int)$row['rueckmeldung_status'] === 0) {
                                echo '<i class="fas fa-times-circle text-danger"></i> Teilnahme abgelehnt';
                            }
                            ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="date" name="schulungstermin" value="<?php echo htmlspecialchars($row['schulungstermin']); ?>" class="form-control d-inline-block" style="width: auto;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="termin_speichern" class="btn btn-warning mt-1">Speichern</button>
                            </form>
                        </td>
						<td><?php echo htmlspecialchars($row['letzte_einladung'] ?? '–'); ?></td>
						<td><?php echo htmlspecialchars($row['unternehmer'] ?? '–'); ?></td>
						<td>
							<?php if ($row['abschluss_prozent'] !== null): ?>
								<?= $row['abschluss_prozent'] ?> %
								<?php if ((int)$row['abschlusstest_bestanden'] === 1): ?>
									<i class="fas fa-check text-success" title="Bestanden"></i>
								<?php else: ?>
									<i class="fas fa-times text-danger" title="Nicht bestanden"></i>
								<?php endif; ?>
							<?php else: ?>
								<span class="text-muted">–</span>
							<?php endif; ?>
						</td>
						<?php if ($berechtigt): ?>
							<td>
								<form method="POST" class="d-inline">
									<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
									<input type="hidden" name="status" value="1">
									<button type="submit" name="set_bestanden" class="btn btn-success btn-sm">Bestanden</button>
								</form>
								<form method="POST" class="d-inline">
									<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
									<input type="hidden" name="status" value="0">
									<button type="submit" name="set_bestanden" class="btn btn-danger btn-sm">Nicht bestanden</button>
								</form>
							</td>
							<td>
								<div class="d-flex gap-2">
									<a href="versand.php?id=<?php echo $row['id']; ?>" class="btn btn-primary">Einladung senden</a>
									<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-id="<?php echo $row['id']; ?>">
										Löschen
									</button>
									<a href="pdf_generieren.php?id=<?php echo $row['id']; ?>" class="btn btn-info">PDF drucken</a>
								</div>
							</td>
							<td>
								<?php if ($row['gesperrt_bis'] !== null && new DateTime($row['gesperrt_bis']) > new DateTime()): ?>
									<div class="mb-1 text-danger">
										<i class="fas fa-ban"></i> Gesperrt bis <?= date('d.m.y', strtotime($row['gesperrt_bis'])) ?>
									</div>
									<form method="POST" class="d-inline">
										<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
										<button type="submit" name="entsperren" class="btn btn-secondary btn-sm">
											Entsperren
										</button>
									</form>
								<?php else: ?>
									<span class="text-muted">–</span>
								<?php endif; ?>
							</td>
						<?php else: ?>
							<td>
								<?php if ($row['gesperrt_bis'] !== null && new DateTime($row['gesperrt_bis']) > new DateTime()): ?>
									<span class="text-danger"><i class="fas fa-ban"></i> Gesperrt bis <?= date('d.m.y', strtotime($row['gesperrt_bis'])) ?></span>
								<?php else: ?>
									<span class="text-success"><i class="fas fa-check-circle"></i> Nicht gesperrt</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <!-- Löschbestätigungsmodal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteModalLabel">Teilnehmer löschen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body">
            Sind Sie sicher, dass Sie diesen Teilnehmer löschen möchten?
            <input type="hidden" name="delete_id" id="delete_id" value="">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-danger" name="delete_submit">Löschen</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Beim Öffnen des Modals den Teilnehmer-ID in das versteckte Feld schreiben
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var deleteId = button.getAttribute('data-delete-id');
            var modalInput = deleteModal.querySelector('#delete_id');
            modalInput.value = deleteId;
        });
    </script>

</body>
</html>
