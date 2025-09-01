<?php
require_once '../../includes/db.php';
require_once '../../includes/config.php';

require_once __DIR__ . '/../../phpmailer/Exception.php';
require_once __DIR__ . '/../../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('LOGFILE', __DIR__ . '/send_schulung.log');

logMessage("🔄 send_schulung.php wurde gestartet (Pfad: " . __FILE__ . ")");

function logMessage($message) {
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents(LOGFILE, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

function createSchulungsteilnehmerViaApi($vorname, $nachname, $email, $drive_id) {
    logMessage("Erzeuge Schulungsteilnehmer über API: $vorname $nachname <$email>");

    $api_url = 'https://funkschulung.4884.de/api/create_schueler.php';
    $api_key = 'aT$93Lm!xY#7vB8eZ2@rFg5^TqW1oNcK'; // ggf. später aus .env laden

    $data = [
        'api_key' => $api_key,
        'vorname' => $vorname,
        'nachname' => $nachname,
        'email' => $email,
        'drive_id' => $drive_id
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logMessage("API-Antwort [$http_status]: $response");

    return [$http_status, json_decode($response, true)];
}

function sendEmail($vorname, $email, $password) {
    logMessage("Versende E-Mail an: $email");
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

        if (TEST_MODE) {
            $mail->addAddress(TEST_EMAIL, TEST_NAME);
        } else {
            $mail->addAddress($email, $vorname);
            $mail->addBCC('technik@taxi4884.de');
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Herzlich willkommen bei der 4884 – Ihr Funktaxi GmbH!";
		$mail->Body = "
			Hallo $vorname,<br><br>

			herzlich willkommen bei <strong>4884 – Ihr Funktaxi GmbH</strong>! Schön, dass du bald Teil unseres Teams wirst – wir freuen uns auf die Zusammenarbeit mit dir.<br><br>

			Bevor du loslegst, ist die Teilnahme an unserer digitalen <strong>Funkschulung</strong> erforderlich. Sie vermittelt dir die wichtigsten Grundlagen rund um den Funkdienst bei 4884 – flexibel und einfach von zu Hause aus.<br><br>

			<strong>Dein Zugang:</strong><br>
			🔗 <a href='https://funkschulung.4884.de'>funkschulung.4884.de</a><br>
			📧 Benutzername: <strong>$email</strong><br>
			🔐 Passwort: <strong>$password</strong><br><br>

			👉 <strong>Bitte ändere dein Passwort nach dem ersten Login.</strong><br><br>

			<strong>Wichtig:</strong> Bitte bearbeite die Schulung vollständig und gewissenhaft. Am Ende wartet ein kurzer Abschlusstest auf dich.<br><br>

			✅ <strong>Nur wenn der Test bestanden ist, laden wir dich zum Praxistag ein.</strong><br>
			Erst dann zeigen wir dir vor Ort unsere Funktechnik, klären Fragen und bereiten dich auf den Einsatz vor.<br><br>

			Den Termin für den Praxistag bekommst du automatisch, sobald du bereit bist – du musst nichts weiter tun.<br><br>

			Viel Erfolg bei der Schulung – und bis bald bei uns!<br><br>

			Freundliche Grüße<br>
			<strong>Philipp Gausmann</strong> | Technik<br>
			📧 technik@taxi4884.de<br><br>

			—<br>
			<strong>4884 – Ihr Funktaxi GmbH</strong><br>
			Lützner Straße 179, 04179 Leipzig<br>
			📞 Tel: (+49) 0341 / 4949306<br>
			📞 Der Taxiruf: (0341) 4884
		";

        $mail->send();
        logMessage("E-Mail erfolgreich an $vorname ($email) gesendet.");
        return true;
    } catch (Exception $e) {
        logMessage("Fehler beim Senden der E-Mail an $email: {$mail->ErrorInfo}");
        return false;
    }
}

function processNewUsers() {
    logMessage("Starte die Verarbeitung neuer Benutzer...");
    try {
        global $pdo;

        logMessage("Datenbankabfrage wird gestartet...");
        $sql = "SELECT vorname, nachname, email FROM schulungsteilnehmer WHERE processed = 0";
        $stmt = $pdo->query($sql);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logMessage(count($users) . " Benutzer gefunden.");

        if (!empty($users)) {
            foreach ($users as $user) {
                logMessage("Verarbeite Benutzer: " . json_encode($user));
                $vorname = $user['vorname'];
                $nachname = $user['nachname'];
                $email = $user['email'];
                $password = "Taxi4884!"; // initial

                $drive_id = time(); // später evtl. echte ID
                list($status, $response) = createSchulungsteilnehmerViaApi($vorname, $nachname, $email, $drive_id);

                if ($status == 201) {
                    logMessage("Benutzer $vorname $nachname erfolgreich im Schulungstool erstellt.");

                    if (sendEmail($vorname, $email, $password)) {
                        $update_sql = "UPDATE schulungsteilnehmer SET processed = 1 WHERE email = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$email]);
                        logMessage("Benutzer $vorname $nachname als verarbeitet markiert.");
                    } else {
                        logMessage("Fehler beim Senden der E-Mail an $vorname $nachname.");
                    }
                } else {
                    $error_message = $response['message'] ?? "Unbekannter Fehler";
                    logMessage("Fehler beim Erstellen des Benutzers $vorname $nachname: $error_message");
                }
            }
        } else {
            logMessage("Keine neuen Benutzer in der Tabelle 'schulungsteilnehmer'.");
        }
    } catch (PDOException $e) {
        logMessage("Datenbankfehler: " . $e->getMessage());
    }
}

// Start
processNewUsers();
