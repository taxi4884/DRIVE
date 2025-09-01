<?php
// Verbindungen und Konfigurationen einbinden
require_once '../includes/head.php';
require_once '../includes/config.php';
require_once '../includes/logger.php';
require_once '../includes/mailer.php';

// Logfile-Pfad
define('LOGFILE', __DIR__ . '/send_schulung.log');


// Funktion zur Generierung des Benutzernamens
function generateUsername($vorname, $nachname) {
    $vorname = strtolower(substr($vorname, 0, 1));
    $nachname = strtolower(str_replace(' ', '_', $nachname));
    return $vorname . '.' . $nachname;
}

// Funktion zur Erstellung des Benutzers in WordPress
function createWpUser($username, $password, $email, $vorname) {
    logMessage("WordPress-Benutzer wird erstellt: $username", LOGFILE);
    logMessage("API-Daten: " . json_encode($data), LOGFILE);
    $api_url = 'https://funkschulung.taxi4884.de/wp-json/wp/v2/users';
    $api_username = 'createUser';
    $api_password = 'xDar xQbH scHU ARoM Vg45 LcFm';

    $data = [
        'username' => $username,
        'password' => $password,
        'email' => $email,
        'first_name' => $vorname // Vorname wird als Name mitgegeben
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$api_username:$api_password");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logMessage("WordPress-Benutzer-Erstellung abgeschlossen mit Status: $http_status", LOGFILE);
    logMessage("API-Antwort: " . json_encode($response), LOGFILE);
    return [$http_status, json_decode($response, true)];
}

// Hauptlogik
function processNewUsers() {
    logMessage("Starte die Verarbeitung neuer Benutzer...", LOGFILE);
    try {
        global $pdo; // Verwendung der Verbindung aus head.php

        logMessage("Datenbankabfrage wird gestartet...", LOGFILE);
        $sql = "SELECT vorname, nachname, email FROM schulungsteilnehmer WHERE processed = 0";
        $stmt = $pdo->query($sql);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        logMessage(count($users) . " Benutzer gefunden.", LOGFILE);

        if (!empty($users)) {
            foreach ($users as $user) {
                logMessage("Verarbeite Benutzer: " . json_encode($user), LOGFILE);
                $vorname = $user['vorname'];
                $nachname = $user['nachname'];
                $email = $user['email'];

                $username = generateUsername($vorname, $nachname);
                $password = "Taxi4884";

                list($status, $response) = createWpUser($username, $password, $email, $vorname);

                if ($status == 201) {
                    logMessage("Benutzer $vorname $nachname erfolgreich in WordPress erstellt.", LOGFILE);

                    $subject = "Herzlich willkommen bei der 4884 – Ihr Funktaxi GmbH!";
                    $body = "Hallo $vorname,<br><br>"
                        . "herzlich willkommen bei der 4884 – Ihr Funktaxi GmbH! Wir freuen uns darauf, dich bald als neuen Chauffeur bei uns begrüßen zu dürfen.<br><br>"
                        . "Um sicherzustellen, dass du bestens vorbereitet bist, ist die Teilnahme an unserer Funkschulung essenziell. Hier findest du alle Informationen:<br><br>"
                        . "Funkschulung Zugang: <a href='https://taxi4884.de/funkschulung'>taxi4884.de/funkschulung</a><br>"
                        . "Benutzername: $username<br>"
                        . "Passwort: $password<br><br>"
                        . "Bitte absolviere die Schulung gewissenhaft. Anschließend findet ein Praxistag statt an dem wir die Themen abfragen, den Abschlusstest schreiben und die Funkanlage gezeigt wird:<br><br>"
                        . "Der Termin für den Praxistag wird dir frühzeitig bekanntgegeben.<br><br>"
                        . "Wir stehen dir für alle Fragen zur Verfügung und freuen uns, dich an einem der Praxistage persönlich kennenzulernen. Nach erfolgreichem Abschluss des Abschlusstests bist du offiziell ein Teil unseres Fahrerteams!<br><br>"
                        . "Viel Erfolg und bis bald,<br>"
                        . "Philipp Gausmann | Technik<br>"
                        . "technik@taxi4884.de<br><br>"
                        . "E-Mail:  info@taxi4884.de <br>"
                        . "Tel:     (+49) 0341 / 4949306<br><br>"
                        . "4884 – Ihr Funktaxi Älteste Leipziger Funktaxenzentrale GmbH | Lützner Straße 179 | 04179 Leipzig<br>"
                        . "Geschäftsf. Gesellschafter: Thomas Bühnert,Thomas Voigt<br><br>"
                        . "Der Taxiruf: (0341) 4884<br>"
                        . "270 Taxen und mehr ...<br>"
                        . "Limousinen, Kombis, Großraumtaxen<br>"
                        . "Wir kommen wie gerufen!<br>";

                    if (sendEmail($email, $vorname, $subject, $body)) {
                        $update_sql = "UPDATE schulungsteilnehmer SET processed = 1 WHERE email = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$email]);
                        logMessage("Benutzer $vorname $nachname als verarbeitet markiert.", LOGFILE);
                    } else {
                        logMessage("Fehler beim Senden der E-Mail an $vorname $nachname.", LOGFILE);
                    }
                } else {
                    $error_message = isset($response['message']) ? $response['message'] : "Unbekannter Fehler";
                    logMessage("Fehler beim Erstellen des Benutzers $vorname $nachname: $error_message", LOGFILE);
                }
            }
        } else {
            logMessage("Keine neuen Benutzer in der Tabelle 'schulungsteilnehmer'.", LOGFILE);
        }
    } catch (PDOException $e) {
        logMessage("Datenbankfehler: " . $e->getMessage(), LOGFILE);
    }
}

// Starte die Verarbeitung
processNewUsers();
?>
