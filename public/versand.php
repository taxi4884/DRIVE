<?php
require_once '../includes/bootstrap.php';
require_once '../includes/config.php';
require_once '../includes/logger.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer-Klassen einbinden
require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

// Logfile-Pfad
define('LOGFILE', __DIR__ . '/schulung/versand.log');


/**
 * Einladung versenden + iCalendar‑Anhang
 *
 * @param int    $id              Teilnehmer‑ID
 * @param string $vorname         Vorname
 * @param string $email           Empfängeradresse
 * @param string $praxistagdatum  Termin im Format dd.mm.yy (wird hier zurückgewandelt)
 * @return bool  true bei Erfolg
 */
 
// Funktion zum Senden der Einladung
function sendInvitation($id, $vorname, $email, $praxistagdatum) {
    logMessage("Versende Einladung an: $email", LOGFILE);
	
	/* ---------------------------------------------------------------------
	1) Termin in DateTime wandeln
	------------------------------------------------------------------ */
    $dateObj = DateTime::createFromFormat('d.m.y', $praxistagdatum);
    if (!$dateObj) {
        logMessage("Ungültiges Datumsformat: $praxistagdatum", LOGFILE);
        return false;
    }

    // Beginn 09:00 Uhr – Ende 15:00 Uhr (6‑h‑Block)
    $dtStart = clone $dateObj;
    $dtStart->setTime(9, 0);

    $dtEnd = clone $dtStart;
    $dtEnd->modify('+6 hours');

    /* ---------------------------------------------------------------------
       2) ICS‑Text bauen
       ------------------------------------------------------------------ */
    $uid      = uniqid()."@4884.de";
    $dtStamp  = (new DateTime())->format('Ymd\THis\Z');      // UTC
    $dtStartS = $dtStart->format('Ymd\THis');
    $dtEndS   = $dtEnd->format('Ymd\THis');

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
SUMMARY:Praxistag Funkschulung
LOCATION:Lützner Straße 179, 04179 Leipzig
DESCRIPTION:Praxistag zur Funkschulung – bitte um 09:00 Uhr im Büro melden
END:VEVENT
END:VCALENDAR
ICS;

    /* ---------------------------------------------------------------------
	3) PHPMailer setzen & senden
	------------------------------------------------------------------ */

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
        $mail->Subject = "Einladung zum Praxistag der Funkschulung";

        // Links für Rückmeldungen generieren
        $acceptLink = "https://drive.4884.de/schulung/rueckmeldung.php?id=$id&status=1";
        $declineLink = "https://drive.4884.de/schulung/rueckmeldung.php?id=$id&status=0";

        $mail->Body = "
            Hallo $vorname,<br><br>
            Ein aufregender Tag steht bevor! Am <strong>$praxistagdatum</strong> findet unser Praxistag zur Funkschulung statt, und du bist herzlich eingeladen, dabei zu sein.<br><br>
            Da wir nur eine begrenzte Anzahl an Plätzen haben, bitten wir dich uns schnellstmöglich Bescheid zu geben, ob wir mit dir rechnen können.<br><br>
            <a href='$acceptLink' style='padding: 10px 20px; background-color: green; color: white; text-decoration: none; border-radius: 5px;'>✔ Ich nehme teil</a>
            <a href='$declineLink' style='padding: 10px 20px; background-color: red; color: white; text-decoration: none; border-radius: 5px;'>❌ Ich nehme nicht teil</a><br><br>

            Ohne Anmeldung können wir dich leider nicht berücksichtigen.<br><br>

            Treffpunkt ist um 09:00 Uhr im Büro, Lützner Straße 179, 04177 Leipzig. Nach einem kurzen Get-together brechen wir gemeinsam in unseren Schulungsraum in der Werkstatt auf.<br><br>

            📅 <a href='https://drive.4884.de/schulung/download_ics.php?id=$id' target='_blank'>Termin als Kalendereintrag (.ics) herunterladen</a><br><br>
			
			Was wir vorhaben: Ein intensives, 6-stündiges Programm, das dich nicht nur fit im Umgang mit Funkgeräten macht, sondern dir am Ende des Tages – nach erfolgreichem Abschlusstest – deinen Funkausweis in die Hand gibt.<br><br>

            Ich freue mich auf deine Rückmeldung und darauf, dich persönlich zu begrüßen!<br><br>

            Beste Grüße,<br>
            Philipp Gausmann | Technik<br>
            technik@taxi4884.de<br><br>

            E-Mail: info@taxi4884.de<br>
            Tel: (+49) 0341 / 4949306<br><br>

            4884 – Ihr Funktaxi Älteste Leipziger Funktaxenzentrale GmbH | Lützner Straße 179 | 04179 Leipzig<br>
            Geschäftsf. Gesellschafter: Thomas Bühnert, Thomas Voigt<br><br>
        ";
		
		        // Plain‑Text‑Fallback
        $mail->AltBody = "Hallo $vorname,\r\n\r\n"
                       . "am $praxistagdatum findet unser Praxistag statt.\r\n"
                       . "Teilnahme zusagen:  $acceptLink\r\n"
                       . "Teilnahme absagen :  $declineLink\r\n\r\n";

        /* ---------- Abschicken ---------- */
        $mail->send();
        logMessage("E-Mail erfolgreich an $vorname ($email) gesendet.", LOGFILE);
        return true;
    } catch (Exception $e) {
        logMessage("Fehler beim Senden der E-Mail an $email: {$mail->ErrorInfo}", LOGFILE);
        return false;
    }
}

// Nur ausführen, wenn versand.php direkt aufgerufen wurde
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Teilnehmerdaten aus der Datenbank abrufen
        try {
            $query = "SELECT vorname, email, schulungstermin FROM schulungsteilnehmer WHERE id = :id";
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
            $praxistagdatum = $teilnehmer['schulungstermin'];

            // Datum ins Format dd.mm.yy umwandeln
            if (!empty($praxistagdatum)) {
                $date = DateTime::createFromFormat('Y-m-d', $praxistagdatum);
                if ($date) {
                    $praxistagdatum = $date->format('d.m.y');
                }
            }

            if (sendInvitation($id, $vorname, $email, $praxistagdatum)) {
                try {
                    $updateEinladung = "
                        UPDATE schulungsteilnehmer 
                        SET letzte_einladung = 
                            CASE 
                                WHEN schulungstermin IS NOT NULL AND schulungstermin != '' 
                                THEN schulungstermin 
                                ELSE CURDATE() 
                            END 
                        WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateEinladung);
                    $updateStmt->execute([':id' => $id]);
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
