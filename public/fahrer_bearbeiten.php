<?php
require_once '../includes/bootstrap.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Fahrer-ID aus der URL abrufen
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: fahrer.php");
    exit();
}

$fahrer_id = $_GET['id'];

// Fahrer-Daten laden
$stmt = $pdo->prepare("SELECT * FROM Fahrer WHERE FahrerID = ?");
$stmt->execute([$fahrer_id]);
$fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fahrer) {
    header("Location: fahrer.php");
    exit();
}

// Fahrerdaten neu speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $license_validity = $_POST['license_validity'];
    $pschein_validity = $_POST['pschein_validity'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $street = trim($_POST['street']);
    $house_number = trim($_POST['house_number']);
    $zip = trim($_POST['zip']);
    $city = trim($_POST['city']);
    $status = $_POST['status'];
    $personalnummer = trim($_POST['personalnummer']); // <-- Das neue Feld wird erfasst!

    // SQL-Statement zur Aktualisierung der Fahrerdaten
    $stmt = $pdo->prepare("UPDATE Fahrer SET 
        Vorname = ?, 
        Nachname = ?, 
        FuehrerscheinGueltigkeit = ?, 
        PScheinGueltigkeit = ?, 
        Telefonnummer = ?, 
        Email = ?, 
        Strasse = ?, 
        Hausnummer = ?, 
        PLZ = ?, 
        Ort = ?, 
        Status = ?, 
        Personalnummer = ? 
        WHERE FahrerID = ?");

    $stmt->execute([
        $first_name, 
        $last_name, 
        $license_validity, 
        $pschein_validity, 
        $phone, 
        $email, 
        $street, 
        $house_number, 
        $zip, 
        $city, 
        $status, 
        $personalnummer, // <-- Personalnummer wird jetzt gespeichert!
        $fahrer_id
    ]);

    $success = "Fahrerdaten erfolgreich aktualisiert!";
}

// Bußgelder abrufen
$stmt = $pdo->prepare("SELECT date_of_offense, fine_amount, case_number FROM fines WHERE recipient_id = ? ORDER BY date_of_offense DESC");
$stmt->execute([$fahrer_id]);
$bussgelder = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Krankheitszeiträume abrufen
$stmt = $pdo->prepare("SELECT startdatum, enddatum, grund FROM FahrerAbwesenheiten WHERE FahrerID = ? AND abwesenheitsart = 'Krankheit' ORDER BY startdatum DESC");
$stmt->execute([$fahrer_id]);
$krankheiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Urlaubszeiträume abrufen und nach Jahr gruppieren
$stmt = $pdo->prepare("SELECT startdatum, enddatum, grund FROM FahrerAbwesenheiten WHERE FahrerID = ? AND abwesenheitsart = 'Urlaub' ORDER BY startdatum DESC");
$stmt->execute([$fahrer_id]);
$urlaube = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Urlaubstage berechnen
$urlaub_nach_jahr = [];
foreach ($urlaube as $urlaub) {
    $jahr = date('Y', strtotime($urlaub['startdatum']));
    $tage = (new DateTime($urlaub['startdatum']))->diff(new DateTime($urlaub['enddatum']))->days + 1;
    if (!isset($urlaub_nach_jahr[$jahr])) {
        $urlaub_nach_jahr[$jahr]['genommen'] = 0;
        $urlaub_nach_jahr[$jahr]['eintraege'] = [];
    }
    $urlaub_nach_jahr[$jahr]['genommen'] += $tage;
    $urlaub_nach_jahr[$jahr]['eintraege'][] = $urlaub;
}

// Gesamturlaubstage aus der Datenbank abrufen
$stmt = $pdo->prepare("SELECT Urlaubstage FROM Fahrer WHERE FahrerID = ?");
$stmt->execute([$fahrer_id]);
$gesamt_urlaubstage = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrer bearbeiten | DRIVE</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/modal.js"></script>
    <style>
        .form-section {
            margin-bottom: 1.5rem;
        }
        .form-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #ddd;
            padding-bottom: 0.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-section h2 i {
            color: #ffe459;
        }
      label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #555;
            transition: color 0.3s;
        }
        label:hover {
            color: #007bff;
        }
        input, select {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
            transition: transform 0.3s ease;
        }
        input:hover {
            transform: scale(1.02);
        }
        input:focus, select:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }
        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-sm {
            background-color: #6c757d;
            color: #fff;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .btn-sm:hover {
            background-color: #5a6268;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert:hover {
            transform: scale(1.05);
        }
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .section {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <main class="grid-container">
        <section class="section">
            <h2>Persönliche Daten</h2>
            <?php if ($error): ?>
            <div class="alert error"><i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form action="fahrer_bearbeiten.php?id=<?= htmlspecialchars($fahrer_id) ?>" method="POST">
            <div class="form-section">
                <h2><i class="fa-solid fa-user"></i> Persönliche Daten</h2>
                <label for="first_name">Vorname:</label>
                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($fahrer['Vorname']) ?>" required>
                <label for="last_name">Nachname:</label>
                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($fahrer['Nachname']) ?>" required>
            </div>

            <div class="form-section">
                <h2><i class="fa-solid fa-id-card"></i> Dokumente</h2>
                <label for="license_validity">Führerschein gültig bis:</label>
                <input type="date" id="license_validity" name="license_validity" value="<?= htmlspecialchars($fahrer['FuehrerscheinGueltigkeit']) ?>" required>
                <label for="pschein_validity">P-Schein gültig bis:</label>
                <input type="date" id="pschein_validity" name="pschein_validity" value="<?= htmlspecialchars($fahrer['PScheinGueltigkeit']) ?>" required>
            </div>

            <div class="form-section">
                <h2><i class="fa-solid fa-phone"></i> Kontakt</h2>
                <label for="phone">Telefon:</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($fahrer['Telefonnummer']) ?>">
                <label for="email">E-Mail:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($fahrer['Email']) ?>">
            </div>

            <div class="form-section">
                <h2><i class="fa-solid fa-map-marker-alt"></i> Adresse</h2>
                <label for="street">Straße:</label>
                <input type="text" id="street" name="street" value="<?= htmlspecialchars($fahrer['Strasse'] ?? '') ?>" required>
                <label for="house_number">Hausnummer:</label>
                <input type="text" id="house_number" name="house_number" value="<?= htmlspecialchars($fahrer['Hausnummer'] ?? '') ?>" required>
                <label for="zip">PLZ:</label>
                <input type="text" id="zip" name="zip" value="<?= htmlspecialchars($fahrer['PLZ'] ?? '') ?>" required>
                <label for="city">Ort:</label>
                <input type="text" id="city" name="city" value="<?= htmlspecialchars($fahrer['Ort'] ?? '') ?>" required>
            </div>

			<div class="form-section">
				<h2><i class="fa-solid fa-user-check"></i> Status & Personalnummer</h2>

				<label for="personalnummer"><i class="fa-solid fa-id-card"></i> Personalnummer:</label>
				<input type="text" id="personalnummer" name="personalnummer" value="<?= htmlspecialchars($fahrer['Personalnummer'] ?? '') ?>">

				<label for="status"><i class="fa-solid fa-toggle-on"></i> Status:</label>
				<select id="status" name="status">
					<option value="Aktiv" <?= $fahrer['Status'] === 'Aktiv' ? 'selected' : '' ?>>Aktiv</option>
					<option value="Inaktiv" <?= $fahrer['Status'] === 'Inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
				</select>
			</div>

            <button type="submit"><i class="fa-solid fa-save"></i> Speichern</button>
            </form>
        </section>

        <section class="section">
          <div class="form-section">
            <h2>Bußgelder</h2>
            <table class="table">
                <tr><th>Datum</th><th>Betrag</th><th>Aktenzeichen</th></tr>
                <?php foreach ($bussgelder as $bussgeld): ?>
                <tr>
                    <td><?= htmlspecialchars($bussgeld['date_of_offense']) ?></td>
                    <td><?= htmlspecialchars($bussgeld['fine_amount']) ?> €</td>
                    <td><?= htmlspecialchars($bussgeld['case_number']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
          <div class="form-section">
            <h2>Krankheitszeiträume</h2>
            <table class="table">
                <tr><th>Von</th><th>Bis</th><th>Grund</th></tr>
                <?php foreach ($krankheiten as $krankheit): ?>
                <tr>
                    <td><?= htmlspecialchars($krankheit['startdatum']) ?></td>
                    <td><?= htmlspecialchars($krankheit['enddatum']) ?></td>
                    <td><?= htmlspecialchars($krankheit['grund']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
          <div class="form-section">
            <h2><i class="fa-solid fa-plane"></i> Urlaubszeiträume</h2>
            <?php foreach ($urlaub_nach_jahr as $jahr => $daten): ?>
                <h3><?= $jahr ?> (<?= $daten['genommen'] ?>/<?= $gesamt_urlaubstage ?>)</h3>
                <table class="table">
                    <tr><th>Von</th><th>Bis</th><th>Grund</th><th>Aktion</th></tr>
						<?php foreach ($daten['eintraege'] as $urlaub): ?>
							<tr>
								<td><?= htmlspecialchars($urlaub['startdatum']) ?></td>
								<td><?= htmlspecialchars($urlaub['enddatum']) ?></td>
								<td><?= htmlspecialchars($urlaub['grund']) ?></td>
								<td>
									<a href="#" onclick="openEditModal('<?= $urlaub['startdatum'] ?>', '<?= $urlaub['enddatum'] ?>', '<?= htmlspecialchars($urlaub['grund']) ?>')" title="Bearbeiten">
										<i class="fa-solid fa-pen-to-square"></i>
									</a>
									&nbsp;
									<a href="urlaub_loeschen.php?id=<?= urlencode($fahrer_id) ?>&von=<?= urlencode($urlaub['startdatum']) ?>&bis=<?= urlencode($urlaub['enddatum']) ?>" title="Löschen" onclick="return confirm('Eintrag wirklich löschen?')">
										<i class="fa-solid fa-trash"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
                </table>
            <?php endforeach; ?>
            </div>
        </section>
      <p><a href="fahrer.php" class="btn-sm"><i class="fa-solid fa-arrow-left"></i> Zurück zur Fahrerübersicht</a></p>
    </main>
	<div id="editModal" style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
		<div style="background:#fff; padding:2rem; border-radius:8px; width:400px; position:relative;">
			<h2><i class="fa-solid fa-pen-to-square"></i> Urlaub bearbeiten</h2>
			<form id="editUrlaubForm">
				<input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer_id) ?>">
				<input type="hidden" name="original_start" id="original_start">
				<input type="hidden" name="original_end" id="original_end">

				<label>Von:</label>
				<input type="date" name="startdatum" id="edit_startdatum" required>

				<label>Bis:</label>
				<input type="date" name="enddatum" id="edit_enddatum" required>

				<label>Grund:</label>
				<input type="text" name="grund" id="edit_grund" required>

				<button type="submit">Speichern</button>
				<button type="button" onclick="closeEditModal()" style="margin-left:10px;">Abbrechen</button>
			</form>
		</div>
	</div>
	<script>
                function openEditModal(von, bis, grund) {
                        document.getElementById('edit_startdatum').value = von;
                        document.getElementById('edit_enddatum').value = bis;
                        document.getElementById('edit_grund').value = grund;
                        document.getElementById('original_start').value = von;
                        document.getElementById('original_end').value = bis;
                        document.getElementById('editModal').style.display = 'flex';
                }

		function closeEditModal() {
			document.getElementById('editModal').style.display = 'none';
		}

		// AJAX-Formular-Submit
		document.getElementById('editUrlaubForm').addEventListener('submit', function(e) {
			e.preventDefault();
			const formData = new FormData(this);

			fetch('urlaub_update.php', {
				method: 'POST',
				body: formData
			}).then(response => response.text())
			  .then(data => {
				location.reload(); // Seite neu laden nach erfolgreicher Bearbeitung
			});
		});
    </script>
</body>
</html>
