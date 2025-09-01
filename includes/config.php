<?php
// SMTP-Konfiguration
define('SMTP_HOST', 'w01d16ef.kasserver.com');
define('SMTP_USER', 'no-reply@drive.4884.de');
define('SMTP_PASS', '7MUZqVhpnboghkouaWK5');
define('SMTP_PORT', 465); // 587 für STARTTLS oder 465 für SMTPS
define('SMTP_SECURE', 'ssl'); // 'tls' für STARTTLS oder 'ssl' für SMTPS

// Absenderinformationen
define('MAIL_FROM', 'no-reply@drive.4884.de');
define('MAIL_FROM_NAME', 'DRIVE | 4884 - Ihr Funktaxi GmbH');

// Testmodus
define('TEST_MODE', false); // Auf 'false' setzen für die Produktion
define('TEST_EMAIL', 'philipp.gausmann@gmx.de');
define('TEST_NAME', 'Philipp');
?>
