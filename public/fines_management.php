<?php
// public/fines_management.php
require_once '../includes/bootstrap.php';
require_once '../includes/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function fetchDriversWithFines($pdo) {
    $sql = "SELECT d.FahrerID, d.Vorname, d.Nachname, d.Strasse, d.Hausnummer, d.PLZ, d.Ort, d.birth_date, c.name as firmenname, 
            COUNT(DISTINCT f.id) as anzahl_bussgelder
            FROM Fahrer d
            LEFT JOIN Fahrzeuge v ON d.FahrerID = v.FahrerID
            LEFT JOIN companies c ON v.company_id = c.id
            LEFT JOIN fines f ON d.FahrerID = f.driver_id
            GROUP BY d.FahrerID, d.Vorname, d.Nachname, d.Strasse, d.Hausnummer, d.PLZ, d.Ort, d.birth_date, c.name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fine'])) {
    $driverId = $_POST['driver_id'];
    $companyId = $_POST['company_id'];
    $dateOfOffense = $_POST['date_of_offense'];
    $fineAmount = $_POST['fine_amount'];
    $details = $_POST['details'];
    $caseNumber = $_POST['case_number'];

    $recipientId = null;
    $recipientEmail = null;

    if (!empty($_POST['recipient_id']) && is_numeric($_POST['recipient_id'])) {
        $recipientId = $_POST['recipient_id'];
        $stmt = $pdo->prepare("SELECT email FROM recipients WHERE id = ?");
        $stmt->execute([$recipientId]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($recipient) {
            $recipientEmail = $recipient['email'];
        }
    }

    if (!empty($_POST['new_recipient_name']) && !empty($_POST['new_recipient_email'])) {
        $newRecipientName = $_POST['new_recipient_name'];
        $newRecipientEmail = $_POST['new_recipient_email'];

        $stmt = $pdo->prepare("INSERT INTO recipients (name, email) VALUES (?, ?)");
        $stmt->execute([$newRecipientName, $newRecipientEmail]);

        $recipientId = $pdo->lastInsertId();
        $recipientEmail = $newRecipientEmail;
    }

    if (empty($recipientId)) {
        $successMessage = "<p>Fehler: Kein gültiger Empfänger angegeben.</p>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO fines (driver_id, company_id, recipient_id, date_of_offense, fine_amount, details, case_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$driverId, $companyId, $recipientId, $dateOfOffense, $fineAmount, $details, $caseNumber]);

        $driverStmt = $pdo->prepare("SELECT Vorname, Nachname, Strasse, Hausnummer, PLZ, Ort, birth_date, c.name as firmenname FROM Fahrer d LEFT JOIN Fahrzeuge v ON d.FahrerID = v.FahrerID LEFT JOIN companies c ON v.company_id = c.id WHERE d.FahrerID = ?");
        $driverStmt->execute([$driverId]);
        $driverInfo = $driverStmt->fetch(PDO::FETCH_ASSOC);

        if (!$driverInfo) {
            $successMessage = "<p>Fehler: Fahrer-Informationen konnten nicht abgerufen werden.</p>";
        } elseif (!empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $subject = "Aktenzeichen: $caseNumber";
            $message = "Sehr geehrte Damen und Herren,\n\n" .
                       "hinsichtlich des Tatvorwurfs mit dem Aktenzeichen: $caseNumber möchte ich wie folgt Stellung nehmen:\n\n" .
                       "Zum Zeitpunkt des Tatvorwurfs war folgende Person Fahrzeugführer:\n\n" .
                       "Vorname: {$driverInfo['Vorname']}\n" .
                       "Nachname: {$driverInfo['Nachname']}\n" .
                       "Adresse: {$driverInfo['Strasse']} {$driverInfo['Hausnummer']}\n" .
                       "PLZ: {$driverInfo['PLZ']}\n" .
                       "Ort: {$driverInfo['Ort']}\n" .
                       "Geburtsdatum: {$driverInfo['birth_date']}\n\n" .
                       "Bitte senden Sie der angegebenen Person den Tatvorwurf zu.\n\n" .
                       "Mit freundlichen Grüßen\n\n" .
                       "{$driverInfo['firmenname']}\nT. Bühnert &. T. Voigt\nLützner Straße 179\n04179 Leipzig";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($recipientEmail);
                $mail->addCC('verwaltung@taxi4884.de');
                $mail->Subject = $subject;
                $mail->Body = $message;
                $mail->CharSet = 'UTF-8';

                $mail->send();
                $successMessage = "<p>Bußgeld hinzugefügt und Benachrichtigung gesendet!</p>";
            } catch (Exception $e) {
                $successMessage = "<p>Fehler beim Senden der E-Mail: {$mail->ErrorInfo}</p>";
            }
        } else {
            $successMessage = "<p>Fehler: Keine gültige Empfängeradresse vorhanden.</p>";
        }
    }
}

$drivers = fetchDriversWithFines($pdo);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <title>Bußgeldverwaltung | Drive</title>
</head>
<body>
    <?php include 'nav.php'; ?>
    <main>
    <h1>Bußgeldverwaltung</h1>

    <?php if (!empty($successMessage)): ?>
        <section>
            <?= $successMessage ?>
        </section>
    <?php endif; ?>

    <section>
        <h2>Neues Bußgeld hinzufügen</h2>
        <button id="openModal">Bußgeld hinzufügen</button>

        <div id="fineModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <form method="POST">
                    <label for="driver_id">Fahrer:</label>
                    <select name="driver_id" id="driver_id" required>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['FahrerID'] ?>" data-address="<?= htmlspecialchars(json_encode(['Strasse' => $driver['Strasse'], 'Hausnummer' => $driver['Hausnummer'], 'PLZ' => $driver['PLZ'], 'Ort' => $driver['Ort']])) ?>">
                                <?= $driver['Vorname'] . ' ' . $driver['Nachname'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select><br>

                    <label for="company_id">Firma:</label>
                    <select name="company_id" id="company_id" required>
                        <?php
                        $companies = $pdo->query("SELECT * FROM companies")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= $company['name'] ?></option>
                        <?php endforeach; ?>
                    </select><br>

                    <label for="recipient_id">Empfänger:</label>
                    <label for="recipient_id">Empfänger:</label>
					<select name="recipient_id" id="recipient_id">
						<option value="">Vorhandenen auswählen</option>
						<?php
						$recipients = $pdo->query("SELECT * FROM recipients")->fetchAll(PDO::FETCH_ASSOC);
						foreach ($recipients as $recipient): ?>
							<option value="<?= $recipient['id'] ?>" data-email="<?= $recipient['email'] ?>">
								<?= $recipient['name'] ?>
							</option>
						<?php endforeach; ?>
					</select><br>

					<input type="hidden" name="recipient_email" id="recipient_email">

					<label for="new_recipient_name">Neuen Empfänger hinzufügen:</label><br>
					<input type="text" name="new_recipient_name" placeholder="Name"><br>
					<input type="email" name="new_recipient_email" placeholder="E-Mail"><br>

                    <label for="case_number">Aktenzeichen:</label>
                    <input type="text" name="case_number" id="case_number" required><br>

                    <label for="date_of_offense">Datum des Verstoßes:</label>
                    <input type="date" name="date_of_offense" id="date_of_offense" required><br>

                    <label for="fine_amount">Bußgeldbetrag:</label>
                    <input type="number" name="fine_amount" id="fine_amount" required><br>

                    <h3>Adresse des Fahrers</h3>
                    <label for="driver_street">Straße:</label>
                    <input type="text" name="driver_street" id="driver_street"><br>

                    <label for="driver_house_number">Hausnummer:</label>
                    <input type="text" name="driver_house_number" id="driver_house_number"><br>

                    <label for="driver_postal_code">PLZ:</label>
                    <input type="text" name="driver_postal_code" id="driver_postal_code"><br>

                    <label for="driver_city">Ort:</label>
                    <input type="text" name="driver_city" id="driver_city"><br>

                    <label for="details">Details:</label>
                    <textarea name="details" id="details"></textarea><br>

                    <button type="submit" name="add_fine">Bußgeld hinzufügen</button>
                </form>
            </div>
        </div>
    </section>

    <section>
        <h2>Fahrer und Anzahl der Bußgelder</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>Fahrer</th>
                    <th>Anzahl der Bußgelder</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $driver): ?>
                    <tr>
                        <td><?= $driver['Vorname'] . ' ' . $driver['Nachname'] ?></td>
                        <td><?= $driver['anzahl_bussgelder'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    </main>
    <script>
        document.querySelector('.burger-menu').addEventListener('click', () => {
            document.querySelector('.nav-links').classList.toggle('active');
        });

        const modal = document.getElementById("fineModal");
        const btn = document.getElementById("openModal");
        const span = document.getElementsByClassName("close")[0];

        btn.onclick = function() {
            modal.style.display = "block";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Update company and address fields on driver change
        document.getElementById('driver_id').addEventListener('change', function() {
            const selectedDriver = this.options[this.selectedIndex];
            const addressData = JSON.parse(selectedDriver.getAttribute('data-address'));

            document.getElementById('driver_street').value = addressData.Strasse;
            document.getElementById('driver_house_number').value = addressData.Hausnummer;
            document.getElementById('driver_postal_code').value = addressData.PLZ;
            document.getElementById('driver_city').value = addressData.Ort;
        });
		
		document.getElementById('recipient_id').addEventListener('change', function() {
			const selectedOption = this.options[this.selectedIndex];
			const emailField = document.querySelector('input[name="new_recipient_email"]');
			
			if (selectedOption.value) {
				emailField.value = selectedOption.getAttribute('data-email');
			} else {
				emailField.value = ''; // Zurücksetzen, wenn kein Empfänger ausgewählt wurde
			}
		});
    </script>
</body>
</html>
