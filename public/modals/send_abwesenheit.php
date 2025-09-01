<?php
// send_abwesenheit.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../phpmailer/Exception.php';
require_once __DIR__ . '/../../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Funktion zum Senden der Krankmeldung per E-Mail
 */
function sendeKrankmeldungEmail($empfaengerEmail, $empfaengerName, $krankmeldungDetails) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($empfaengerEmail, $empfaengerName);

        $mail->isHTML(true);
        $mail->Subject = 'Neue Krankmeldung in der Zentrale';
        $mail->Body    = "
            <p>Hallo {$empfaengerName},</p>
            <p>Es liegt eine neue Krankmeldung in der Zentrale vor:</p>
            <ul>
                <li>Mitarbeiter Name: {$krankmeldungDetails['vorname']} {$krankmeldungDetails['nachname']}</li>
                <li>Typ: {$krankmeldungDetails['typ']}</li>
                <li>Startdatum: {$krankmeldungDetails['startdatum']}</li>
                <li>Enddatum: {$krankmeldungDetails['enddatum']}</li>
                <li>Bemerkungen: {$krankmeldungDetails['bemerkungen']}</li>
            </ul>
            <p>Viele Grüße,<br>Dein Unternehmen</p>
        ";
        $mail->AltBody = "Hallo {$empfaengerName},\n\nEs liegt eine neue Krankmeldung in der Zentrale vor:\n- Mitarbeiter Name: {$krankmeldungDetails['vorname']} {$krankmeldungDetails['nachname']}\n- Typ: {$krankmeldungDetails['typ']}\n- Startdatum: {$krankmeldungDetails['startdatum']}\n- Enddatum: {$krankmeldungDetails['enddatum']}\n- Bemerkungen: {$krankmeldungDetails['bemerkungen']}\n\nViele Grüße,\nDein Unternehmen";

        $mail->send();
    } catch (Exception $e) {
        error_log("E-Mail konnte nicht an {$empfaengerEmail} gesendet werden. Fehler: {$mail->ErrorInfo}");
    }
}
?>
