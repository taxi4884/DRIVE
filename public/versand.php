<?php
if (!defined('VERSAND_SKIP_BOOTSTRAP') || VERSAND_SKIP_BOOTSTRAP !== true) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

$versandMailerPath = __DIR__ . '/schulung/versand_mailer.php';
if (file_exists($versandMailerPath)) {
    require_once $versandMailerPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Logfile-Pfad
if (!defined('LOGFILE')) {
    define('LOGFILE', __DIR__ . '/schulung/versand.log');
}


/**
 * Einladung versenden + iCalendar‑Anhang
 *
 * @param int    $id              Teilnehmer‑ID
 * @param string $vorname         Vorname
 * @param string $email           Empfängeradresse
 * @param string $praxistagdatum  Termin im Format dd.mm.yy (wird hier zurückgewandelt)
 * @return bool  true bei Erfolg
 */
 
// Funktion zum Protokollieren der Einladungshistorie
function protokolliereEinladung($teilnehmerId, $terminId = null, $stufe = null)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO schulungseinladungen (teilnehmer_id, termin_id, stufe)
             VALUES (:teilnehmer_id, :termin_id, :stufe)"
        );

        $stmt->execute([
            ':teilnehmer_id' => $teilnehmerId,
            ':termin_id'     => $terminId ?: null,
            ':stufe'         => $stufe !== null ? $stufe : null,
        ]);
    } catch (PDOException $e) {
        logMessage(
            "Fehler beim Protokollieren der Einladung für Teilnehmer-ID $teilnehmerId: " . $e->getMessage(),
            LOGFILE
        );
    }
}

// Funktion zum Senden der Einladung
if (!function_exists('sendInvitation')) {
function sendInvitation($id, $vorname, $email, $stufe) {
    logMessage("Versende Einladung an: $email", LOGFILE);

    $mail = new PHPMailer(true);

    try {
        // Servereinstellungen
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        // Absenderinformationen
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email, $vorname);
		$mail->addBCC('technik@taxi4884.de');

        // Inhalt der E-Mail
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Einladung zur Schulungsstufe $stufe";

        $auswahlLink = "https://drive.4884.de/schulung/terminauswahl.php?id=$id";

        $mail->Body = "
            Hallo $vorname,<br><br>

            schön, dass du den nächsten Schritt gehst!<br>
            Wir laden dich hiermit herzlich zur <strong>aktuellen Schulungsstufe $stufe</strong> ein.<br><br>

            Die Schulungsgebühr für diese Stufe beträgt <strong>25,00&nbsp;€</strong>.<br><br>

            Bitte reserviere dir über den folgenden Link verbindlich einen passenden Termin,
            damit wir deinen Platz für die Schulung einplanen können:<br><br>

            <a href='$auswahlLink' style='padding: 10px 20px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>
            🗓️ Jetzt Termin reservieren & teilnehmen
            </a><br><br>

            Nach der Terminreservierung erhältst du automatisch eine Bestätigung per E-Mail.<br><br>

            Wir freuen uns auf deine Teilnahme!<br><br>

            Beste Grüße,<br>
            Philipp Gausmann | Technik<br>
            technik@taxi4884.de<br><br>
            E-Mail: info@taxi4884.de<br>
            Tel: (+49) 0341 / 4949300<br><br>
            4884 – Ihr Funktaxi Älteste Leipziger Funktaxenzentrale GmbH | Lützner Straße 179 | 04179 Leipzig<br>
            Geschäftsf. Gesellschafter: Thomas Bühnert, Thomas Voigt<br><br>
        ";

        $mail->AltBody = "Hallo $vorname,\r\n\r\n"
                       . "schön, dass du den nächsten Schritt gehst!\r\n"
                       . "Wir laden dich hiermit herzlich zur aktuellen Schulungsstufe $stufe ein.\r\n\r\n"
                       . "Die Schulungsgebühr für diese Stufe beträgt 25,00 €.\r\n\r\n"
                       . "Bitte reserviere dir über den folgenden Link verbindlich einen passenden Termin,\r\n"
                       . "damit wir deinen Platz für die Schulung einplanen können:\r\n"
                       . "$auswahlLink\r\n\r\n"
                       . "Nach der Terminreservierung erhältst du automatisch eine Bestätigung per E-Mail.\r\n\r\n"
                       . "Wir freuen uns auf deine Teilnahme!\r\n\r\n"
                       . "Beste Grüße,\r\n"
                       . "Philipp Gausmann | Technik\r\n"
                       . "technik@taxi4884.de\r\n\r\n"
                       . "E-Mail: info@taxi4884.de\r\n"
                       . "Tel: (+49) 0341 / 4949300\r\n\r\n"
                       . "4884 – Ihr Funktaxi Älteste Leipziger Funktaxenzentrale GmbH | Lützner Straße 179 | 04179 Leipzig\r\n"
                       . "Geschäftsf. Gesellschafter: Thomas Bühnert, Thomas Voigt\r\n";

        /* ---------- Abschicken ---------- */
        $mail->send();
        logMessage("E-Mail erfolgreich an $vorname ($email) gesendet.", LOGFILE);
        return true;
    } catch (Exception $e) {
        logMessage("Fehler beim Senden der E-Mail an $email: {$mail->ErrorInfo}", LOGFILE);
        return false;
    }
}
}

if (!function_exists('sendTerminBestaetigung')) {
function sendTerminBestaetigung($vorname, $email, $termin, $stufe, $teilnehmerId) {
    $mail = new PHPMailer(true);

    $dateObj = new DateTime($termin);
    if (!$dateObj) {
        logMessage("Ungültiges Datumsformat für Terminbestätigung: $termin", LOGFILE);
        return false;
    }

    $dtStart = clone $dateObj;
    $dtEnd = (clone $dtStart)->modify('+6 hours');

    $uid = uniqid() . "@4884.de";
    $dtStamp = (new DateTime())->format('Ymd\THis\Z');
    $dtStartS = $dtStart->format('Ymd\THis');
    $dtEndS = $dtEnd->format('Ymd\THis');

    $startZeit = $dtStart->format('H:i');

    $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Taxi4884//Funkschulung//DE
METHOD:REQUEST
BEGIN:VEVENT
UID:$uid
DTSTAMP:$dtStamp
DTSTART:$dtStartS
DTEND:$dtEndS
SUMMARY:Schulungstermin Stufe $stufe
LOCATION:Lützner Straße 179, 04179 Leipzig
DESCRIPTION:Schulungstermin Stufe $stufe – bitte um $startZeit Uhr im Büro melden
END:VEVENT
END:VCALENDAR
ICS;

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email, $vorname);
        $mail->addBCC('technik@taxi4884.de');

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Terminbestätigung Schulungsstufe $stufe";

        $terminDe = $dateObj->format('d.m.Y');
        $terminUhrzeit = $dateObj->format('H:i');
        $icsLink = "https://drive.4884.de/schulung/download_ics.php?id=$teilnehmerId";

        $mail->Body = "
            Hallo $vorname,<br><br>

            vielen Dank für deine Terminreservierung!<br>
            Dein Termin für die <strong>Schulungsstufe $stufe</strong> ist hiermit verbindlich bestätigt.<br><br>

            <strong>Datum:</strong> $terminDe<br>
            <strong>Uhrzeit:</strong> $terminUhrzeit Uhr<br>
            <strong>Ort:</strong> Lützner Straße 179, 04179 Leipzig<br><br>

            📅 <a href='$icsLink' target='_blank'>Termin als Kalendereintrag (.ics) speichern</a><br><br>

            Wir freuen uns, dich zur Schulung begrüßen zu dürfen.<br><br>

            Bis bald!<br>
            Philipp Gausmann | Technik<br>
            technik@taxi4884.de<br><br>
            E-Mail: info@taxi4884.de<br>
            Tel: (+49) 0341 / 4949300<br><br>
            4884 – Ihr Funktaxi Älteste Leipziger Funktaxenzentrale GmbH | Lützner Straße 179 | 04179 Leipzig<br>
            Geschäftsf. Gesellschafter: Thomas Bühnert, Thomas Voigt<br><br>
        ";

        $mail->AltBody = "Hallo $vorname,\r\n\r\n"
            . "vielen Dank für deine Terminreservierung!\r\n"
            . "Dein Termin für die Schulungsstufe $stufe ist hiermit verbindlich bestätigt.\r\n\r\n"
            . "Datum: $terminDe\r\n"
            . "Uhrzeit: $terminUhrzeit Uhr\r\n"
            . "Ort: Lützner Straße 179, 04179 Leipzig\r\n\r\n"
            . "Termin als Kalendereintrag (.ics) speichern: $icsLink\r\n\r\n"
            . "Wir freuen uns, dich zur Schulung begrüßen zu dürfen.\r\n\r\n"
            . "Bis bald!\r\n"
            . "Philipp Gausmann | Technik\r\n"
            . "technik@taxi4884.de\r\n\r\n"
            . "E-Mail: info@taxi4884.de\r\n"
            . "Tel: (+49) 0341 / 4949300\r\n\r\n"
            . "4884 – Ihr Funktaxi Älteste Leipziger Funktaxenzentrale GmbH | Lützner Straße 179 | 04179 Leipzig\r\n"
            . "Geschäftsf. Gesellschafter: Thomas Bühnert, Thomas Voigt\r\n";

        $mail->send();
        logMessage("Terminbestätigung an $vorname ($email) gesendet.", LOGFILE);
        return true;
    } catch (Exception $e) {
        logMessage("Fehler beim Senden der Terminbestätigung an $email: {$mail->ErrorInfo}", LOGFILE);
        return false;
    }
}
}

// Nur ausführen, wenn versand.php direkt aufgerufen wurde
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Teilnehmerdaten aus der Datenbank abrufen
        try {
            $query = "SELECT vorname, email, stufe FROM schulungsteilnehmer WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            $teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            logMessage("Datenbankfehler: " . $e->getMessage(), LOGFILE);
            die("<p>Fehler bei der Datenbankabfrage.</p>");
        }

        if ($teilnehmer) {
            $vorname = $teilnehmer['vorname'];
            $email = $teilnehmer['email'];
            $stufe = (int) ($teilnehmer['stufe'] ?? 0);

            if (sendInvitation($id, $vorname, $email, $stufe)) {
                protokolliereEinladung($id, null, $stufe);

                try {
                    $updateEinladung = "
                        UPDATE schulungsteilnehmer 
                        SET letzte_einladung = CURDATE(),
                            letzte_einladung_stufe = :stufe
                        WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateEinladung);
                    $updateStmt->execute([':id' => $id, ':stufe' => $stufe]);
                    logMessage("letzte_einladung für Teilnehmer-ID $id aktualisiert.", LOGFILE);
                } catch (PDOException $e) {
                    logMessage("Fehler beim Aktualisieren von letzte_einladung: " . $e->getMessage(), LOGFILE);
                }

                session_start();
                $_SESSION['message'] = "Die Einladung wurde erfolgreich an $vorname gesendet.";
                header("Location: schulungsverwaltung.php");
                exit;
            } else {
                session_start();
                $_SESSION['message'] = "Fehler beim Senden der Einladung an $vorname.";
                header("Location: schulungsverwaltung.php");
                exit;
            }
        } else {
            echo "<p>Teilnehmer nicht gefunden.</p>";
        }
    } else {
        echo "<p>Keine Teilnehmer-ID angegeben.</p>";
    }
}
?>
