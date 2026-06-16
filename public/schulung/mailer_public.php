<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/logger.php';
require_once __DIR__ . '/../../phpmailer/Exception.php';
require_once __DIR__ . '/../../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!defined('LOGFILE')) {
    define('LOGFILE', __DIR__ . '/versand.log');
}

if (!function_exists('sendTerminBestaetigung')) {
    function sendTerminBestaetigung($vorname, $email, $termin, $stufe, $teilnehmerId)
    {
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
