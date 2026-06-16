<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using the project SMTP settings.
 *
 * @param string $to Recipient email address
 * @param string $name Recipient name
 * @param string $subject Email subject
 * @param string $body HTML email body
 * @param array  $attachments Array of attachments. Each attachment can be
 *                            specified as:
 *                            ['path' => '/path/to/file', 'name' => 'optional_name']
 *                            or ['string' => $data, 'filename' => 'name.csv',
 *                                'type' => 'text/csv', 'encoding' => 'base64']
 *
 * @return bool True on success, false on failure
 */
function sendEmail($to, $name, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        if (TEST_MODE) {
            $mail->addAddress(TEST_EMAIL, TEST_NAME);
        } else {
            $mail->addAddress($to, $name);
        }

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        foreach ($attachments as $attachment) {
            if (isset($attachment['path'])) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
            } elseif (isset($attachment['string']) && isset($attachment['filename'])) {
                $type     = $attachment['type'] ?? 'application/octet-stream';
                $encoding = $attachment['encoding'] ?? 'base64';
                $mail->addStringAttachment($attachment['string'], $attachment['filename'], $encoding, $type);
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
