<?php
// test_email.php

// Fehleranzeige aktivieren (nur für Debugging, in der Produktion deaktivieren)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Konfigurationsdatei einbinden
require_once __DIR__ . '/../../includes/config.php';

// PHPMailer-Klassen einbinden
require_once __DIR__ . '/../../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/SMTP.php';
require_once __DIR__ . '/../../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server-Einstellungen
    $mail->SMTPDebug = 2; // Für detaillierte Debug-Ausgabe
    $mail->Debugoutput = 'error_log'; // Debug-Ausgabe an das Fehlerprotokoll senden
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE; // 'tls' oder 'ssl'
    $mail->Port       = SMTP_PORT;

    // Absender und Empfänger
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(TEST_EMAIL, TEST_NAME); // Empfängeradresse

    // Inhalt der E-Mail
    $mail->isHTML(true);
    $mail->Subject = 'Test E-Mail mit PHPMailer';
    $mail->Body    = "<p>Dies ist eine <strong>Test-E-Mail</strong> von PHPMailer.</p>";
    $mail->AltBody = "Dies ist eine Test-E-Mail von PHPMailer.";

    $mail->send();
    echo 'Test-E-Mail wurde erfolgreich gesendet.';
} catch (Exception $e) {
    echo "Test-E-Mail konnte nicht gesendet werden. Fehler: {$mail->ErrorInfo}";
}
?>
