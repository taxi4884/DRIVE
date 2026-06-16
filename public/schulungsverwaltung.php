<?php
// schulungsverwaltung.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/bootstrap.php';
require_once __DIR__ . '/versand.php';

const FMS_ENDPOINT = 'https://4884gateway.de/fms';

function getNextFahrerNummer(PDO $pdo): int
{
    $pdo->beginTransaction();

    try {
        $row = $pdo->query("SELECT letzte_nummer FROM fahrernummer_counter FOR UPDATE")
            ->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Tabelle fahrernummer_counter ist leer.');
        }

        $next = ((int) $row['letzte_nummer']) + 1;

        $stmt = $pdo->prepare("UPDATE fahrernummer_counter SET letzte_nummer = :n");
        $stmt->execute([':n' => $next]);

        $pdo->commit();
        return $next;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function sixDigitCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendSetFahrer(array $payload): array
{
    $ch = curl_init(FMS_ENDPOINT);
    $headers = ['Content-Type: application/json'];

    if (defined('FMS_BEARER') && FMS_BEARER !== '') {
        $headers[] = 'Authorization: Bearer ' . FMS_BEARER;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parsed = null;
    if (is_string($response) && $response !== '') {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $parsed = $decoded;
        }
    }

    $gatewaySuccess = null;
    $gatewayCode = null;
    $gatewayMessage = null;

    if (is_array($parsed)) {
        if (array_key_exists('success', $parsed)) {
            $gatewaySuccess = filter_var($parsed['success'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if (array_key_exists('code', $parsed)) {
            $gatewayCode = (string) $parsed['code'];
            if ($gatewaySuccess === null) {
                $gatewaySuccess = ($gatewayCode === '0');
            }
        }
        if (array_key_exists('message', $parsed) && is_scalar($parsed['message'])) {
            $gatewayMessage = (string) $parsed['message'];
        }
    }

    $ok = ($err === '' && $code >= 200 && $code < 300 && ($gatewaySuccess !== false));

    return [
        'ok' => $ok,
        'http_code' => $code,
        'response' => $response ?? '',
        'error' => $err ?: null,
        'gateway_success' => $gatewaySuccess,
        'gateway_code' => $gatewayCode,
        'gateway_message' => $gatewayMessage,
        'parsed' => $parsed,
    ];
}

function buildFmsPayload(array $teilnehmer, int $fahrerNummer): array
{
    $plz = $teilnehmer['postleitzahl'] ?? '';
    $geburtsdatum = '';
    if (!empty($teilnehmer['geburtsdatum'])) {
        $dt = DateTime::createFromFormat('Y-m-d', (string) $teilnehmer['geburtsdatum']);
        if ($dt) {
            $geburtsdatum = $dt->format('d.m.Y');
        }
    }

    $mobil = isset($teilnehmer['handynummer'])
        ? preg_replace('/\s+/', '', (string) $teilnehmer['handynummer'])
        : '';

    $vertragsbeginn = (new DateTime('today'))->format('d.m.Y');
    $aktivbis = (new DateTime('today'))->modify('+6 months')->format('d.m.Y');

    return [
        'SETFAHRER' => [
            'FAHRER_NR'          => (string) $fahrerNummer,
            'DISPLAYNUMMER'      => (string) $fahrerNummer,
            'ANMELDECODE'        => sixDigitCode(),
            'UNTERNEHMER_NR'     => '1200',
            'VERTRAGSBEGINN'     => $vertragsbeginn,
            'VORNAME'            => $teilnehmer['vorname'] ?? '',
            'NACHNAME'           => $teilnehmer['nachname'] ?? '',
            'STRASSE_NAME'       => $teilnehmer['strasse'] ?? '',
            'HAUSNUMMER_ECKE'    => $teilnehmer['hausnummer'] ?? '',
            'PLZ'                => $plz,
            'ORT_NAME'           => $teilnehmer['ort'] ?? '',
            'GEBURTSDATUM'       => $geburtsdatum,
            'MOBILTELEFONNUMMER' => $mobil,
            'AKTIV_BIS'          => $aktivbis,
        ],
    ];
}

function versendeEinladungenFuerStufe($stufe)
{
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT id, vorname, email
           FROM schulungsteilnehmer
          WHERE stufe = :stufe
            AND (gesperrt_bis IS NULL OR gesperrt_bis <= CURDATE())
            AND (letzte_einladung IS NULL OR letzte_einladung < CURDATE())
          ORDER BY erstellt_am ASC"
    );
    $stmt->execute([':stufe' => $stufe]);

    $teilnehmerListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $versendet = 0;
    $fehlgeschlagen = 0;

    foreach ($teilnehmerListe as $teilnehmer) {
        $id = (int) $teilnehmer['id'];
        $vorname = $teilnehmer['vorname'];
        $email = $teilnehmer['email'];

        if (sendInvitation($id, $vorname, $email, $stufe)) {
            protokolliereEinladung($id, null, $stufe);

            $update = $pdo->prepare(
                "UPDATE schulungsteilnehmer
                    SET letzte_einladung = CURDATE(),
                        letzte_einladung_stufe = :stufe
                  WHERE id = :id"
            );
            $update->execute([':id' => $id, ':stufe' => $stufe]);
            $versendet++;
        } else {
            $fehlgeschlagen++;
        }
    }

    return [$versendet, $fehlgeschlagen];
}


// Teilnehmer automatisch freigeben, deren Sperrfrist abgelaufen ist
$freigabeQuery = "
    UPDATE schulungsteilnehmer 
    SET gesperrt_bis = NULL, nicht_bestanden_count = 0 
    WHERE gesperrt_bis IS NOT NULL AND gesperrt_bis <= CURDATE()
";
$pdo->exec($freigabeQuery);

// Verwaltungsberechtigung prüfen
$berechtigt = false;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT Schulungsverwaltung FROM Benutzer WHERE BenutzerID = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $berechtigt = $stmt->fetchColumn() == 1;
}

// Teilnehmer aus der Datenbank abrufen
$query = "SELECT st.id,
                 st.vorname,
                 st.nachname,
                 st.fms_fahrer_nr,
                 st.fms_anmeldecode,
                 st.email,
                 st.handynummer,
                 st.strasse,
                 st.hausnummer,
                 st.postleitzahl,
                 st.ort,
                 st.geburtsdatum,
                 st.stufe,
                 DATE_FORMAT(st.erstellt_am, '%d.%m.%Y') AS erstellt_am,
                 t.termin AS schulungstermin,
                 DATE_FORMAT(st.letzte_einladung, '%d.%m.%Y') AS letzte_einladung,
                 st.letzte_einladung_stufe,
                 st.rueckmeldung_status,
                 st.unternehmer,
                 st.gesperrt_bis,
                 st.nicht_bestanden_count,
                 st.abschlusstest_bestanden,
                 st.abschluss_prozent,
                 st.letzter_themen_id,
                 st.schulungstermin_id
          FROM schulungsteilnehmer st
          LEFT JOIN schulungstermine t ON st.schulungstermin_id = t.id
          ORDER BY STR_TO_DATE(st.erstellt_am, '%Y-%m-%d') DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$teilnehmer = $stmt->fetchAll(PDO::FETCH_ASSOC);

$einladungshistorie = [];

try {
    $historyQuery = "
        SELECT
            se.teilnehmer_id,
            DATE_FORMAT(se.eingeladen_am, '%d.%m.%Y %H:%i') AS eingeladen_am,
            DATE_FORMAT(t.termin, '%d.%m.%Y')               AS termin,
            se.stufe
        FROM schulungseinladungen se
        LEFT JOIN schulungstermine t ON se.termin_id = t.id
        ORDER BY se.eingeladen_am DESC";

    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute();

    foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $historyRow) {
        $teilnehmerId = (int) $historyRow['teilnehmer_id'];

        if (!isset($einladungshistorie[$teilnehmerId])) {
            $einladungshistorie[$teilnehmerId] = [];
        }

        $einladungshistorie[$teilnehmerId][] = [
            'eingeladen_am' => $historyRow['eingeladen_am'],
            'termin'        => $historyRow['termin'],
            'stufe'         => $historyRow['stufe'],
        ];
    }
} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht – dann einfach keine Historie anzeigen.
}

$einladungshistorieJson = json_encode(
    $einladungshistorie,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

if ($einladungshistorieJson === false) {
    $einladungshistorieJson = '{}';
}

// Teilnehmerdetails aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teilnehmer'])) {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($id <= 0) {
        $_SESSION['message'] = 'Der Teilnehmer konnte nicht aktualisiert werden.';
        header('Location: schulungsverwaltung.php');
        exit();
    }

    $payload = [
        'vorname'             => trim($_POST['vorname'] ?? ''),
        'nachname'            => trim($_POST['nachname'] ?? ''),
        'fms_fahrer_nr'       => trim($_POST['fms_fahrer_nr'] ?? ''),
        'fms_anmeldecode'     => trim($_POST['fms_anmeldecode'] ?? ''),
        'email'               => trim($_POST['email'] ?? ''),
        'handynummer'         => trim($_POST['handynummer'] ?? ''),
        'strasse'             => trim($_POST['strasse'] ?? ''),
        'hausnummer'          => trim($_POST['hausnummer'] ?? ''),
        'postleitzahl'        => trim($_POST['postleitzahl'] ?? ''),
        'ort'                 => trim($_POST['ort'] ?? ''),
        'unternehmer'         => trim($_POST['unternehmer'] ?? ''),
        'schulungstermin_id'  => trim($_POST['schulungstermin_id'] ?? ''),
    ];

    $geburtsdatum = trim($_POST['geburtsdatum'] ?? '');
    $geburtsdatumValue = null;

    if ($geburtsdatum !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $geburtsdatum);
        if ($date && $date->format('Y-m-d') === $geburtsdatum) {
            $geburtsdatumValue = $date->format('Y-m-d');
        } else {
            $_SESSION['message'] = 'Das eingegebene Geburtsdatum ist ungültig.';
            header('Location: schulungsverwaltung.php');
            exit();
        }
    }

    if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = 'Die angegebene E-Mail-Adresse ist ungültig.';
        header('Location: schulungsverwaltung.php');
        exit();
    }

    try {
        $currentStmt = $pdo->prepare('SELECT schulungstermin_id, rueckmeldung_status FROM schulungsteilnehmer WHERE id = :id');
        $currentStmt->execute([':id' => $id]);
        $currentValues = $currentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentValues) {
            $_SESSION['message'] = 'Teilnehmer wurde nicht gefunden.';
            header('Location: schulungsverwaltung.php');
            exit();
        }

        $currentTerminId = $currentValues['schulungstermin_id'];
        $currentRueckmeldungStatus = $currentValues['rueckmeldung_status'];
        $schulungsterminId = null;
        $rueckmeldungStatus = $currentRueckmeldungStatus;

        if ($payload['schulungstermin_id'] !== '') {
            if (!ctype_digit($payload['schulungstermin_id'])) {
                $_SESSION['message'] = 'Der ausgewählte Schulungstermin ist ungültig.';
                header('Location: schulungsverwaltung.php');
                exit();
            }

            $terminId = (int) $payload['schulungstermin_id'];
            $terminStmt = $pdo->prepare('SELECT id FROM schulungstermine WHERE id = :id');
            $terminStmt->execute([':id' => $terminId]);
            if (!$terminStmt->fetchColumn()) {
                $_SESSION['message'] = 'Der ausgewählte Schulungstermin wurde nicht gefunden.';
                header('Location: schulungsverwaltung.php');
                exit();
            }

            $schulungsterminId = $terminId;
            if ((string) $currentTerminId !== (string) $terminId) {
                $rueckmeldungStatus = null;
            }
        } else {
            $schulungsterminId = null;
            if ($currentTerminId !== null) {
                $rueckmeldungStatus = null;
            }
        }

        $updateStmt = $pdo->prepare('
            UPDATE schulungsteilnehmer
               SET vorname = :vorname,
                   nachname = :nachname,
                   fms_fahrer_nr = :fms_fahrer_nr,
                   fms_anmeldecode = :fms_anmeldecode,
                   email = :email,
                   handynummer = :handynummer,
                   strasse = :strasse,
                   hausnummer = :hausnummer,
                   postleitzahl = :postleitzahl,
                   ort = :ort,
                   unternehmer = :unternehmer,
                   geburtsdatum = :geburtsdatum,
                   schulungstermin_id = :schulungstermin_id,
                   rueckmeldung_status = :rueckmeldung_status
             WHERE id = :id
        ');

        foreach ($payload as $key => $value) {
            $payload[$key] = $value === '' ? null : $value;
        }

        $updateStmt->execute([
            ':vorname'      => $payload['vorname'],
            ':nachname'     => $payload['nachname'],
            ':fms_fahrer_nr' => $payload['fms_fahrer_nr'],
            ':fms_anmeldecode' => $payload['fms_anmeldecode'],
            ':email'        => $payload['email'],
            ':handynummer'  => $payload['handynummer'],
            ':strasse'      => $payload['strasse'],
            ':hausnummer'   => $payload['hausnummer'],
            ':postleitzahl' => $payload['postleitzahl'],
            ':ort'          => $payload['ort'],
            ':unternehmer'  => $payload['unternehmer'],
            ':geburtsdatum' => $geburtsdatumValue,
            ':schulungstermin_id' => $schulungsterminId,
            ':rueckmeldung_status' => $rueckmeldungStatus,
            ':id'           => $id,
        ]);

        $_SESSION['message'] = 'Die Teilnehmerdaten wurden erfolgreich aktualisiert.';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Fehler beim Aktualisieren der Teilnehmerdaten: ' . $e->getMessage();
    }

    header('Location: schulungsverwaltung.php');
    exit();
}

// Termin anlegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['termin_anlegen'])) {
    $terminDatum = $_POST['termin_datum'] ?? '';
    $terminUhrzeit = $_POST['termin_uhrzeit'] ?? '';
    $terminStufe = isset($_POST['termin_stufe']) ? (int) $_POST['termin_stufe'] : null;

    if ($terminDatum && $terminUhrzeit && $terminStufe !== null) {
        try {
            $terminDateTime = sprintf('%s %s:00', $terminDatum, $terminUhrzeit);
            $insert = $pdo->prepare("INSERT INTO schulungstermine (termin, stufe) VALUES (:termin, :stufe)");
            $insert->execute([':termin' => $terminDateTime, ':stufe' => $terminStufe]);
            $_SESSION['message'] = 'Termin wurde erfolgreich angelegt.';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Fehler beim Anlegen des Termins: ' . $e->getMessage();
        }
    }

    header("Location: schulungsverwaltung.php");
    exit();
}

// Termin löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['termin_delete_submit'])) {
    $terminId = (int) ($_POST['termin_delete_id'] ?? 0);

    if ($terminId > 0) {
        try {
            $pdo->beginTransaction();

            $resetTeilnehmer = $pdo->prepare("
                UPDATE schulungsteilnehmer
                   SET schulungstermin_id = NULL,
                       rueckmeldung_status = NULL
                 WHERE schulungstermin_id = :termin_id
            ");
            $resetTeilnehmer->execute([':termin_id' => $terminId]);

            $deleteTermin = $pdo->prepare("DELETE FROM schulungstermine WHERE id = :termin_id");
            $deleteTermin->execute([':termin_id' => $terminId]);

            $pdo->commit();
            $_SESSION['message'] = 'Termin wurde erfolgreich gelöscht.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'Fehler beim Löschen des Termins: ' . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = 'Ungültiger Termin für das Löschen.';
    }

    header("Location: schulungsverwaltung.php");
    exit();
}

// Einladungen für eine Stufe versenden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['einladen_stufe'])) {
    $stufe = isset($_POST['stufe']) ? (int) $_POST['stufe'] : null;
    if ($stufe !== null) {
        [$versendet, $fehlgeschlagen] = versendeEinladungenFuerStufe($stufe);
        $_SESSION['message'] = "Einladungen versendet: $versendet. Fehlgeschlagen: $fehlgeschlagen.";
    }

    header("Location: schulungsverwaltung.php");
    exit();
}

// Teilnehmer löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submit'])) {
    $delete_id = (int)$_POST['delete_id'];
    try {
        $deleteQuery = "DELETE FROM schulungsteilnehmer WHERE id = :id";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([':id' => $delete_id]);
        $_SESSION['message'] = "Teilnehmer erfolgreich gelöscht.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Löschen des Teilnehmers: " . $e->getMessage();
    }
    header("Location: schulungsverwaltung.php");
    exit();
}

// Rückmeldungen zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rueckmeldung_zuruecksetzen'])) {
    try {
        $resetQuery = "UPDATE schulungsteilnehmer SET rueckmeldung_status = NULL";
        $resetStmt = $pdo->prepare($resetQuery);
        $resetStmt->execute();
        $_SESSION['message'] = "Alle Rückmeldungen wurden erfolgreich zurückgesetzt.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Zurücksetzen der Rückmeldungen: " . $e->getMessage();
    }
    header("Location: schulungsverwaltung.php");
    exit();
}

// Versand an FMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_fms'])) {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['message'] = 'Ungültige Teilnehmer-ID für den FMS-Versand.';
        header('Location: schulungsverwaltung.php');
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT vorname, nachname, strasse, hausnummer, postleitzahl, ort, geburtsdatum, handynummer
            FROM schulungsteilnehmer
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teilnehmer) {
            $_SESSION['message'] = 'Teilnehmer wurde für den FMS-Versand nicht gefunden.';
            header('Location: schulungsverwaltung.php');
            exit();
        }

        $fahrerNummer = getNextFahrerNummer($pdo);
        $payload = buildFmsPayload($teilnehmer, $fahrerNummer);
        $res = sendSetFahrer($payload);

        if (!$res['ok']) {
            $parts = [];
            if (!empty($res['error'])) {
                $parts[] = 'cURL: ' . $res['error'];
            }
            if (!empty($res['gateway_code'])) {
                $parts[] = 'Gateway-Code: ' . $res['gateway_code'];
            }
            if (!empty($res['gateway_message'])) {
                $parts[] = 'Gateway-Meldung: ' . $res['gateway_message'];
            }
            if (empty($parts) && !empty($res['response'])) {
                $parts[] = 'Antwort: ' . $res['response'];
            }

            $_SESSION['message'] = sprintf(
                "FMS-Versand fehlgeschlagen (HTTP %d)%s",
                $res['http_code'],
                !empty($parts) ? ': ' . implode(' | ', $parts) : '.'
            );
        } else {
            $code = (string)($payload['SETFAHRER']['ANMELDECODE'] ?? '');
            $saveStmt = $pdo->prepare('UPDATE schulungsteilnehmer SET fms_fahrer_nr = :fahrer_nr, fms_anmeldecode = :anmeldecode WHERE id = :id');
            $saveStmt->execute([':fahrer_nr' => (string)$fahrerNummer, ':anmeldecode' => $code !== '' ? $code : null, ':id' => $id]);
            $_SESSION['message'] = "FMS-Versand erfolgreich (Fahrer-Nr. {$fahrerNummer}).";
        }
    } catch (Throwable $e) {
        $_SESSION['message'] = 'Fehler beim FMS-Versand: ' . $e->getMessage();
    }

    header('Location: schulungsverwaltung.php');
    exit();
}

// Statistik auslesen
$statsQuery = "SELECT COUNT(*) AS gesamt FROM schulungsteilnehmer";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$stufenStats = [];
$stufenStatsStmt = $pdo->query("SELECT stufe, COUNT(*) AS anzahl FROM schulungsteilnehmer GROUP BY stufe ORDER BY stufe ASC");
foreach ($stufenStatsStmt->fetchAll(PDO::FETCH_ASSOC) as $stufeRow) {
    $stufenStats[(int) $stufeRow['stufe']] = (int) $stufeRow['anzahl'];
}

// Schulungsergebnis speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_bestanden'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status']; // 1 = bestanden, 0 = nicht bestanden

    try {
        if ($status === 1) {
            $stufeStmt = $pdo->prepare("SELECT stufe FROM schulungsteilnehmer WHERE id = :id");
            $stufeStmt->execute([':id' => $id]);
            $aktuelleStufe = (int) $stufeStmt->fetchColumn();

            if ($aktuelleStufe >= 3) {
                $stmt = $pdo->prepare("DELETE FROM schulungsteilnehmer WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $_SESSION['message'] = "Teilnehmer hat Stufe 3 bestanden und wurde gelöscht.";
            } else {
                $neueStufe = $aktuelleStufe + 1;
                $updateStmt = $pdo->prepare("
                    UPDATE schulungsteilnehmer
                       SET bestanden_status = 1,
                           stufe = :stufe,
                           schulungstermin_id = NULL,
                           rueckmeldung_status = NULL
                     WHERE id = :id
                ");
                $updateStmt->execute([
                    ':stufe' => $neueStufe,
                    ':id' => $id,
                ]);
                $_SESSION['message'] = "Teilnehmer hat bestanden und ist jetzt in Stufe $neueStufe.";
            }
        } else {
            // Teilnehmer hat nicht bestanden → Count ermitteln und erhöhen
            $stmt = $pdo->prepare("SELECT nicht_bestanden_count FROM schulungsteilnehmer WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $count = (int)$stmt->fetchColumn();

            $count++; // um eins erhöhen

            // Sperrfrist berechnen: 3x nicht bestanden → 90 Tage, sonst 14 Tage
            $sperrfristTage = $count >= 3 ? 90 : 14;
            $sperrBis = (new DateTime())->modify("+$sperrfristTage days")->format('Y-m-d');

            // Aktualisieren: Status, Zähler und Sperrdatum
            $updateStmt = $pdo->prepare("
                UPDATE schulungsteilnehmer 
                SET bestanden_status = 0,
                    nicht_bestanden_count = :count,
                    gesperrt_bis = :sperr_bis,
                    schulungstermin_id = NULL,
                    rueckmeldung_status = NULL
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':count' => $count,
                ':sperr_bis' => $sperrBis,
                ':id' => $id
            ]);

            $_SESSION['message'] = "Teilnehmer wurde auf 'nicht bestanden' gesetzt und für $sperrfristTage Tage gesperrt.";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Speichern des Ergebnisses: " . $e->getMessage();
    }

    header("Location: schulungsverwaltung.php");
    exit();
}

// Entsperrung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entsperren'])) {
    $id = (int)$_POST['id'];
    try {
        $entsperrQuery = "UPDATE schulungsteilnehmer 
                          SET gesperrt_bis = NULL, nicht_bestanden_count = 0 
                          WHERE id = :id";
        $entsperrStmt = $pdo->prepare($entsperrQuery);
        $entsperrStmt->execute([':id' => $id]);
        $_SESSION['message'] = "Teilnehmer wurde erfolgreich entsperrt.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Fehler beim Entsperren: " . $e->getMessage();
    }
    header("Location: schulungsverwaltung.php");
    exit();
}
$termine = $pdo->query("
    SELECT id,
           stufe,
           termin,
           DATE_FORMAT(termin, '%d.%m.%Y %H:%i') AS termin_de
    FROM schulungstermine
    ORDER BY termin ASC
")->fetchAll(PDO::FETCH_ASSOC);

$terminTeilnehmerCounts = [];
$terminBestaetigtCounts = [];
$terminTeilnehmer = [];

if (!empty($termine)) {
    $terminIds = array_column($termine, 'id');
    $placeholders = implode(',', array_fill(0, count($terminIds), '?'));
    $countQuery = "
        SELECT schulungstermin_id,
               COUNT(*) AS teilnehmer,
               SUM(
                   CASE
                       WHEN rueckmeldung_status = 1
                            OR (rueckmeldung_status IS NULL AND schulungstermin_id IS NOT NULL)
                       THEN 1
                       ELSE 0
                   END
               ) AS bestaetigt
          FROM schulungsteilnehmer
         WHERE schulungstermin_id IN ($placeholders)
         GROUP BY schulungstermin_id
    ";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($terminIds);
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $countRow) {
        $terminId = (int) $countRow['schulungstermin_id'];
        $terminTeilnehmerCounts[$terminId] = (int) $countRow['teilnehmer'];
        $terminBestaetigtCounts[$terminId] = (int) $countRow['bestaetigt'];
    }

    $teilnehmerQuery = "
        SELECT id,
               vorname,
               nachname,
               email,
               handynummer,
               rueckmeldung_status,
               schulungstermin_id
          FROM schulungsteilnehmer
         WHERE schulungstermin_id IN ($placeholders)
         ORDER BY nachname ASC, vorname ASC
    ";
    $teilnehmerStmt = $pdo->prepare($teilnehmerQuery);
    $teilnehmerStmt->execute($terminIds);
    foreach ($teilnehmerStmt->fetchAll(PDO::FETCH_ASSOC) as $teilnehmerRow) {
        $terminId = (int) $teilnehmerRow['schulungstermin_id'];
        if (!isset($terminTeilnehmer[$terminId])) {
            $terminTeilnehmer[$terminId] = [];
        }
        $terminTeilnehmer[$terminId][] = [
            'id' => (int) $teilnehmerRow['id'],
            'name' => trim(($teilnehmerRow['vorname'] ?? '') . ' ' . ($teilnehmerRow['nachname'] ?? '')),
            'email' => $teilnehmerRow['email'],
            'handynummer' => $teilnehmerRow['handynummer'],
            'rueckmeldung_status' => $teilnehmerRow['rueckmeldung_status'],
        ];
    }
}

$termineJson = json_encode(
    $termine,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
$terminTeilnehmerJson = json_encode(
    $terminTeilnehmer,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
if ($termineJson === false) {
    $termineJson = '[]';
}
if ($terminTeilnehmerJson === false) {
    $terminTeilnehmerJson = '{}';
}

?>
<?php
$title = 'Schulungsverwaltung';
include __DIR__ . '/../includes/layout.php';
?>
    <!-- Bootstrap CSS für einheitliches Styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        [data-participant-row] {
            cursor: pointer;
        }
    </style>

        <main>
	<div class="alert alert-info text-center">
		<strong>Teilnehmer gesamt:</strong> <?php echo (int) ($stats['gesamt'] ?? 0); ?>
		<?php foreach ($stufenStats as $stufe => $anzahl): ?>
			| <strong>Stufe <?php echo (int) $stufe; ?>:</strong> <?php echo (int) $anzahl; ?>
		<?php endforeach; ?>
	</div>

        <h1>Schulungsverwaltung</h1>
		
			<!-- Nachricht aus der Session anzeigen -->
                        <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert alert-success text-center fade show" data-session-message>
					<?php echo $_SESSION['message']; ?>
				</div>
				<?php unset($_SESSION['message']); ?>
			<?php endif; ?>
		
                <?php if ($berechtigt): ?>
                        <div class="training-actions d-flex flex-wrap align-items-center gap-3 mb-4">
                                <!-- Termin anlegen -->
                                <form method="POST" class="d-flex flex-wrap align-items-center gap-2 mb-0">
                                        <label for="termin_datum" class="form-label mb-0">Neuer Termin:</label>
                                        <input type="date" id="termin_datum" name="termin_datum" class="form-control" required>
                                        <input type="time" id="termin_uhrzeit" name="termin_uhrzeit" class="form-control" required>
                                        <select name="termin_stufe" class="form-select" required>
                                                <option value="" disabled selected>Stufe wählen</option>
                                                <option value="0">Stufe 0</option>
                                                <option value="1">Stufe 1</option>
                                                <option value="2">Stufe 2</option>
                                                <option value="3">Stufe 3</option>
                                        </select>
                                        <button type="submit" name="termin_anlegen" class="btn btn-warning flex-shrink-0">Termin anlegen</button>
                                </form>

                                <!-- Einladungen versenden -->
                                <form method="POST" class="d-flex flex-wrap align-items-center gap-2 mb-0">
                                        <label for="einladen_stufe" class="form-label mb-0">Einladen für Stufe:</label>
                                        <select id="einladen_stufe" name="stufe" class="form-select" required>
                                                <option value="" disabled selected>Stufe wählen</option>
                                                <option value="0">Stufe 0</option>
                                                <option value="1">Stufe 1</option>
                                                <option value="2">Stufe 2</option>
                                                <option value="3">Stufe 3</option>
                                        </select>
                                        <button type="submit" name="einladen_stufe" class="btn btn-primary flex-shrink-0">Einladungen senden</button>
                                </form>

                                <!-- Rückmeldungen zurücksetzen -->
                                <form method="POST" class="mb-0">
                                        <button type="submit" name="rueckmeldung_zuruecksetzen" class="btn btn-danger">
                                                Rückmeldungen zurücksetzen
                                        </button>
                                </form>

                                <!-- Alle PDFs -->
                                <form method="GET" action="pdf_alle_bestaetigt.php" class="mb-0">
                                        <button type="submit" class="btn btn-success">
                                                <i class="fas fa-file-pdf"></i> Alle PDF für bestätigte Teilnehmer
                                        </button>
                                </form>

                                <form action="/schulung/abfrage_status_schulung.php" method="post" class="mb-0">
                                        <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i> Status aus Funkschulung abrufen
                                        </button>
                                </form>
                        </div>
                 <?php endif; ?>

        <p class="text-muted small">Tipp: Doppelklick auf einen Teilnehmer zeigt Details zur Einladungshistorie. Doppelklick auf einen Termin zeigt die zugehörigen Teilnehmer.</p>

        <?php if (!empty($termine)): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Geplante Termine</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Stufe</th>
                                    <th>Teilnehmer</th>
                                    <?php if ($berechtigt): ?>
                                        <th>PDF bestätigte Teilnehmer</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($termine as $termin): ?>
                                    <tr
                                        data-termin-row
                                        data-termin-id="<?php echo (int) $termin['id']; ?>"
                                        data-termin-zeitpunkt="<?php echo htmlspecialchars($termin['termin_de'], ENT_QUOTES); ?>"
                                        data-termin-stufe="<?php echo (int) $termin['stufe']; ?>"
                                    >
                                        <td><?php echo htmlspecialchars($termin['termin_de']); ?></td>
                                        <td>Stufe <?php echo (int) $termin['stufe']; ?></td>
                                        <?php
                                            $terminId = (int) $termin['id'];
                                            $teilnehmerCount = $terminTeilnehmerCounts[$terminId] ?? 0;
                                            $bestaetigtCount = $terminBestaetigtCounts[$terminId] ?? 0;
                                        ?>
                                        <td>
                                            <span class="fw-semibold"><?php echo (int) $teilnehmerCount; ?></span>
                                            <span class="text-muted small">(bestätigt: <?php echo (int) $bestaetigtCount; ?>)</span>
                                        </td>
                                        <?php if ($berechtigt): ?>
                                            <td>
                                                <?php if ($bestaetigtCount > 0): ?>
                                                    <a class="btn btn-sm btn-success" href="pdf_alle_bestaetigt.php?termin_id=<?php echo $terminId; ?>">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">
                                                        Keine Bestätigten
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabelle mit Teilnehmern -->
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
					<th>Status</th>
                    <th>Vorname</th>
                    <th>Nachname</th>
                    <th>Fahrernummer</th>
                    <th>Fahrercode</th>
                    <th>Angemeldet am</th>
                    <th>Stufe</th>
                    <th>Terminwahl</th>
                    <th>Gewählter Termin</th>
					<th>Letzte Einladung</th>
					<th>Unternehmer</th>
					<th>Funkschulung</th>
					<?php if ($berechtigt): ?>
						<th>Schulungsergebnis</th>
						<th>Aktionen</th>
						<th>Verwaltung</th>
					<?php else: ?>
						<th>Sperrstatus</th>
					<?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teilnehmer as $row): ?>
                    <tr
                        data-participant-row
                        data-id="<?php echo (int) $row['id']; ?>"
                        data-vorname="<?php echo htmlspecialchars($row['vorname'] ?? '', ENT_QUOTES); ?>"
                        data-nachname="<?php echo htmlspecialchars($row['nachname'] ?? '', ENT_QUOTES); ?>"
                        data-name="<?php echo htmlspecialchars($row['vorname'] . ' ' . $row['nachname'], ENT_QUOTES); ?>"
                        data-fms-fahrer-nr="<?php echo htmlspecialchars($row['fms_fahrer_nr'] ?? '', ENT_QUOTES); ?>"
                        data-fms-anmeldecode="<?php echo htmlspecialchars($row['fms_anmeldecode'] ?? '', ENT_QUOTES); ?>"
                        data-email="<?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?>"
                        data-handynummer="<?php echo htmlspecialchars($row['handynummer'] ?? '', ENT_QUOTES); ?>"
                        data-unternehmer="<?php echo htmlspecialchars($row['unternehmer'] ?? '', ENT_QUOTES); ?>"
                        data-strasse="<?php echo htmlspecialchars($row['strasse'] ?? '', ENT_QUOTES); ?>"
                        data-hausnummer="<?php echo htmlspecialchars($row['hausnummer'] ?? '', ENT_QUOTES); ?>"
                        data-postleitzahl="<?php echo htmlspecialchars($row['postleitzahl'] ?? '', ENT_QUOTES); ?>"
                        data-ort="<?php echo htmlspecialchars($row['ort'] ?? '', ENT_QUOTES); ?>"
                        data-geburtsdatum="<?php echo htmlspecialchars($row['geburtsdatum'] ?? '', ENT_QUOTES); ?>"
                        data-schulungstermin="<?php echo htmlspecialchars($row['schulungstermin'] ?? '', ENT_QUOTES); ?>"
                        data-schulungstermin-id="<?php echo htmlspecialchars($row['schulungstermin_id'] ?? '', ENT_QUOTES); ?>"
                        data-letzte-einladung="<?php echo htmlspecialchars($row['letzte_einladung'] ?? '', ENT_QUOTES); ?>"
                        data-rueckmeldung-status="<?php echo htmlspecialchars($row['rueckmeldung_status'] ?? '', ENT_QUOTES); ?>"
                        data-stufe="<?php echo htmlspecialchars($row['stufe'] ?? '', ENT_QUOTES); ?>"
                        data-einladungen-anzahl="<?php echo isset($einladungshistorie[$row['id']]) ? count($einladungshistorie[$row['id']]) : 0; ?>"
                        data-erstellt-am="<?php echo htmlspecialchars($row['erstellt_am'] ?? '', ENT_QUOTES); ?>"
                        title="Doppelklick für Details"
                    >
						<td>
							<?php if (!empty($row['gesperrt_bis']) && new DateTime($row['gesperrt_bis']) > new DateTime()): ?>
								<i class="fas fa-ban text-danger" title="Gesperrt bis <?= date('d.m.y', strtotime($row['gesperrt_bis'])) ?>"></i>
							<?php elseif ((int)$row['nicht_bestanden_count'] > 0): ?>
								<i class="fas fa-rotate-left text-warning" title="Wiederholer (bereits durchgefallen)"></i>
							<?php else: ?>
								<i class="fas fa-user-plus text-success" title="Neuer Teilnehmer"></i>
							<?php endif; ?>
						</td>
                        <td><?php echo htmlspecialchars($row['vorname']); ?></td>
                        <td><?php echo htmlspecialchars($row['nachname']); ?></td>
                        <td><?php echo htmlspecialchars($row['fms_fahrer_nr'] ?? '–'); ?></td>
                        <td><?php echo htmlspecialchars($row['fms_anmeldecode'] ?? '–'); ?></td>
                        <td><?php echo htmlspecialchars($row['erstellt_am']); ?></td>
                        <td>
                            Stufe <?php echo (int) $row['stufe']; ?>
                        </td>
                        <td>
                            <?php
                            if ($row['schulungstermin_id']) {
                                echo '<i class="fas fa-check-circle text-success"></i> Termin bestätigt';
                            } else {
                                echo '<i class="fas fa-hourglass-half text-warning"></i> Noch offen';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($row['schulungstermin'])) {
                                echo htmlspecialchars((new DateTime($row['schulungstermin']))->format('d.m.Y H:i'));
                            } else {
                                echo '–';
                            }
                            ?>
                        </td>
						<td><?php echo htmlspecialchars($row['letzte_einladung'] ?? '–'); ?></td>
						<td><?php echo htmlspecialchars($row['unternehmer'] ?? '–'); ?></td>
						<td>
							<?php if ($row['abschluss_prozent'] !== null): ?>
								<?= $row['abschluss_prozent'] ?> %
								<?php if ((int)$row['abschlusstest_bestanden'] === 1): ?>
									<i class="fas fa-check text-success" title="Bestanden"></i>
								<?php else: ?>
									<i class="fas fa-times text-danger" title="Nicht bestanden"></i>
								<?php endif; ?>
							<?php else: ?>
								<span class="text-muted">–</span>
							<?php endif; ?>
						</td>
						<?php if ($berechtigt): ?>
							<td>
								<form method="POST" class="d-inline">
									<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
									<input type="hidden" name="status" value="1">
									<button type="submit" name="set_bestanden" class="btn btn-success btn-sm">Bestanden</button>
								</form>
								<form method="POST" class="d-inline">
									<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
									<input type="hidden" name="status" value="0">
									<button type="submit" name="set_bestanden" class="btn btn-danger btn-sm">Nicht bestanden</button>
								</form>
							</td>
							<td>
								<div class="d-flex gap-2">
									<a href="versand.php?id=<?php echo $row['id']; ?>" class="btn btn-primary">Einladung senden</a>
									<form method="POST" class="d-inline">
										<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
										<button type="submit" name="send_fms" class="btn btn-warning">an FMS senden</button>
									</form>
									<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-id="<?php echo $row['id']; ?>">
										Löschen
									</button>
									<a href="pdf_generieren.php?id=<?php echo $row['id']; ?>" class="btn btn-info">PDF drucken</a>
								</div>
							</td>
							<td>
								<?php if ($row['gesperrt_bis'] !== null && new DateTime($row['gesperrt_bis']) > new DateTime()): ?>
									<div class="mb-1 text-danger">
										<i class="fas fa-ban"></i> Gesperrt bis <?= date('d.m.y', strtotime($row['gesperrt_bis'])) ?>
									</div>
									<form method="POST" class="d-inline">
										<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
										<button type="submit" name="entsperren" class="btn btn-secondary btn-sm">
											Entsperren
										</button>
									</form>
								<?php else: ?>
									<span class="text-muted">–</span>
								<?php endif; ?>
							</td>
						<?php else: ?>
							<td>
								<?php if ($row['gesperrt_bis'] !== null && new DateTime($row['gesperrt_bis']) > new DateTime()): ?>
									<span class="text-danger"><i class="fas fa-ban"></i> Gesperrt bis <?= date('d.m.y', strtotime($row['gesperrt_bis'])) ?></span>
								<?php else: ?>
									<span class="text-success"><i class="fas fa-check-circle"></i> Nicht gesperrt</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <!-- Teilnehmerdetails -->
    <div class="modal fade" id="participantModal" tabindex="-1" aria-labelledby="participantModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="participantModalLabel">Teilnehmerdetails</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body" data-participant-modal-body>
            <p class="text-muted mb-0">Keine Daten vorhanden.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Termin-Details -->
    <div class="modal fade" id="terminModal" tabindex="-1" aria-labelledby="terminModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="terminModalLabel">Terminübersicht</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body" data-termin-modal-body>
            <p class="text-muted mb-0">Keine Daten vorhanden.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTerminModal" data-termin-delete-button>
              Termin löschen
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Löschbestätigungsmodal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteModalLabel">Teilnehmer löschen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body">
            Sind Sie sicher, dass Sie diesen Teilnehmer löschen möchten?
            <input type="hidden" name="delete_id" id="delete_id" value="">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-danger" name="delete_submit">Löschen</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Termin-Löschbestätigungsmodal -->
    <div class="modal fade" id="deleteTerminModal" tabindex="-1" aria-labelledby="deleteTerminModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteTerminModalLabel">Termin löschen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
          </div>
          <div class="modal-body">
            Sind Sie sicher, dass Sie diesen Termin löschen möchten? Zugeordnete Teilnehmer werden ausgetragen.
            <input type="hidden" name="termin_delete_id" id="termin_delete_id" value="">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-danger" name="termin_delete_submit">Termin löschen</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const participantHistory = <?php echo $einladungshistorieJson ?: '{}'; ?>;
        const termine = <?php echo $termineJson ?: '[]'; ?>;
        const terminTeilnehmer = <?php echo $terminTeilnehmerJson ?: '{}'; ?>;
        const participantRows = document.querySelectorAll('[data-participant-row]');
        const terminRows = document.querySelectorAll('[data-termin-row]');
        const participantModalElement = document.getElementById('participantModal');
        const participantModalBody = participantModalElement ? participantModalElement.querySelector('[data-participant-modal-body]') : null;
        const participantModalTitle = participantModalElement ? participantModalElement.querySelector('.modal-title') : null;
        const terminModalElement = document.getElementById('terminModal');
        const terminModalBody = terminModalElement ? terminModalElement.querySelector('[data-termin-modal-body]') : null;
        const terminModalTitle = terminModalElement ? terminModalElement.querySelector('.modal-title') : null;
        const terminDeleteButton = terminModalElement ? terminModalElement.querySelector('[data-termin-delete-button]') : null;

        function normalizeValue(value, fallback = '–') {
            return value === undefined || value === null || value === '' ? fallback : value;
        }

        function getRawValue(value) {
            return value === undefined || value === null ? '' : value;
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function buildAddress(street, houseNumber, postalCode, city) {
            const firstLine = [street, houseNumber].filter(Boolean).join(' ').trim();
            const secondLine = [postalCode, city].filter(Boolean).join(' ').trim();
            const lines = [];

            if (firstLine) {
                lines.push(escapeHtml(firstLine));
            }

            if (secondLine) {
                lines.push(escapeHtml(secondLine));
            }

            return lines.length ? lines.join('<br>') : '–';
        }

        function formatDateGerman(value) {
            if (!value) {
                return '–';
            }

            const [datePart, timePart] = value.split(' ');
            const parts = datePart.split('-');
            if (parts.length === 3) {
                const formattedDate = `${parts[2]}.${parts[1]}.${parts[0]}`;
                if (timePart) {
                    return `${formattedDate} ${timePart.slice(0, 5)}`;
                }
                return formattedDate;
            }

            return value;
        }

        function formatRueckmeldung(status) {
            if (status === '1') {
                return 'Termin bestätigt';
            }

            if (status === '0') {
                return 'Termin abgelehnt';
            }

            return 'Noch offen';
        }

        function buildTerminOptions(selectedId) {
            const selectedEmpty = selectedId ? '' : ' selected';
            const emptyOption = `<option value=""${selectedEmpty}>Kein Termin (auschecken)</option>`;
            const options = termine.map(function (termin) {
                const terminId = String(termin.id);
                const selected = selectedId === terminId ? ' selected' : '';
                const label = `${termin.termin_de} (Stufe ${termin.stufe})`;
                return `<option value="${escapeHtml(terminId)}"${selected}>${escapeHtml(label)}</option>`;
            }).join('');
            return emptyOption + options;
        }

        function showParticipantModal(row) {
            if (!participantModalElement || !participantModalBody || !participantModalTitle) {
                return;
            }

            const participantId = row.dataset.id;
            const participantName = normalizeValue(row.dataset.name, 'Teilnehmer');

            const participantVorname = getRawValue(row.dataset.vorname);
            const participantNachname = getRawValue(row.dataset.nachname);
            const participantEmailRaw = getRawValue(row.dataset.email);
            const participantEmailDisplay = normalizeValue(participantEmailRaw);
            const participantFmsFahrerNrRaw = getRawValue(row.dataset.fmsFahrerNr);
            const participantFmsFahrerNrDisplay = normalizeValue(participantFmsFahrerNrRaw);
            const participantFmsAnmeldecodeRaw = getRawValue(row.dataset.fmsAnmeldecode);
            const participantFmsAnmeldecodeDisplay = normalizeValue(participantFmsAnmeldecodeRaw);
            const participantUnternehmerRaw = getRawValue(row.dataset.unternehmer);
            const participantUnternehmerDisplay = normalizeValue(participantUnternehmerRaw);
            const participantPhoneRaw = getRawValue(row.dataset.handynummer);
            const participantPhoneDisplay = normalizeValue(participantPhoneRaw);
            const participantStreet = getRawValue(row.dataset.strasse);
            const participantHouseNumber = getRawValue(row.dataset.hausnummer);
            const participantPostalCode = getRawValue(row.dataset.postleitzahl);
            const participantCity = getRawValue(row.dataset.ort);
            const participantBirthdate = getRawValue(row.dataset.geburtsdatum);
            const participantBirthdateDisplay = formatDateGerman(participantBirthdate);
            const participantTermin = formatDateGerman(row.dataset.schulungstermin);
            const participantTerminId = getRawValue(row.dataset.schulungsterminId);
            const participantLetzteEinladung = normalizeValue(row.dataset.letzteEinladung);
            const rueckmeldungStatus = formatRueckmeldung(row.dataset.rueckmeldungStatus);
            const participantStufe = normalizeValue(row.dataset.stufe);
            const erstelltAm = normalizeValue(row.dataset.erstelltAm);
            const history = participantHistory[participantId] || [];
            const invitesCount = history.length;
            const addressHtml = buildAddress(
                participantStreet,
                participantHouseNumber,
                participantPostalCode,
                participantCity
            );

            const summaryHtml = `
                <dl class="row mb-0">
                    <dt class="col-sm-5">E-Mail</dt><dd class="col-sm-7">${escapeHtml(participantEmailDisplay)}</dd>
                    <dt class="col-sm-5">Telefon</dt><dd class="col-sm-7">${escapeHtml(participantPhoneDisplay)}</dd>
                    <dt class="col-sm-5">Fahrernummer</dt><dd class="col-sm-7">${escapeHtml(participantFmsFahrerNrDisplay)}</dd>
                    <dt class="col-sm-5">Fahrercode</dt><dd class="col-sm-7">${escapeHtml(participantFmsAnmeldecodeDisplay)}</dd>
                    <dt class="col-sm-5">Adresse</dt><dd class="col-sm-7">${addressHtml}</dd>
                    <dt class="col-sm-5">Geburtsdatum</dt><dd class="col-sm-7">${escapeHtml(participantBirthdateDisplay)}</dd>
                    <dt class="col-sm-5">Unternehmer</dt><dd class="col-sm-7">${escapeHtml(participantUnternehmerDisplay)}</dd>
                    <dt class="col-sm-5">Angemeldet am</dt><dd class="col-sm-7">${escapeHtml(erstelltAm)}</dd>
                    <dt class="col-sm-5">Stufe</dt><dd class="col-sm-7">${escapeHtml(participantStufe)}</dd>
                    <dt class="col-sm-5">Aktueller Schulungstermin</dt><dd class="col-sm-7">${escapeHtml(participantTermin)}</dd>
                    <dt class="col-sm-5">Letzte Einladung</dt><dd class="col-sm-7">${escapeHtml(participantLetzteEinladung)}</dd>
                    <dt class="col-sm-5">Rückmeldestatus</dt><dd class="col-sm-7">${escapeHtml(rueckmeldungStatus)}</dd>
                    <dt class="col-sm-5">Einladungen gesamt</dt><dd class="col-sm-7">${invitesCount}</dd>
                </dl>
            `;

            const formIdPrefix = `editParticipant-${participantId}`;
            const editFormHtml = `
                <h6 class="fw-semibold">Daten bearbeiten</h6>
                <form method="POST" class="row g-3" autocomplete="off">
                    <input type="hidden" name="update_teilnehmer" value="1">
                    <input type="hidden" name="id" value="${participantId}">
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-vorname">Vorname</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-vorname" name="vorname" value="${escapeHtml(participantVorname)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-nachname">Nachname</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-nachname" name="nachname" value="${escapeHtml(participantNachname)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-email">E-Mail</label>
                        <input type="email" class="form-control" id="${formIdPrefix}-email" name="email" value="${escapeHtml(participantEmailRaw)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-handynummer">Telefonnummer</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-handynummer" name="handynummer" value="${escapeHtml(participantPhoneRaw)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-fms_fahrer_nr">Fahrernummer</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-fms_fahrer_nr" name="fms_fahrer_nr" value="${escapeHtml(participantFmsFahrerNrRaw)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-fms_anmeldecode">Fahrercode / PIN</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-fms_anmeldecode" name="fms_anmeldecode" value="${escapeHtml(participantFmsAnmeldecodeRaw)}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="${formIdPrefix}-strasse">Straße</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-strasse" name="strasse" value="${escapeHtml(participantStreet)}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="${formIdPrefix}-hausnummer">Hausnummer</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-hausnummer" name="hausnummer" value="${escapeHtml(participantHouseNumber)}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="${formIdPrefix}-postleitzahl">PLZ</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-postleitzahl" name="postleitzahl" value="${escapeHtml(participantPostalCode)}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="${formIdPrefix}-ort">Ort</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-ort" name="ort" value="${escapeHtml(participantCity)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-geburtsdatum">Geburtsdatum</label>
                        <input type="date" class="form-control" id="${formIdPrefix}-geburtsdatum" name="geburtsdatum" value="${escapeHtml(participantBirthdate)}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="${formIdPrefix}-unternehmer">Unternehmer</label>
                        <input type="text" class="form-control" id="${formIdPrefix}-unternehmer" name="unternehmer" value="${escapeHtml(participantUnternehmerRaw)}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="${formIdPrefix}-schulungstermin">Schulungstermin</label>
                        <select class="form-select" id="${formIdPrefix}-schulungstermin" name="schulungstermin_id">
                            ${buildTerminOptions(participantTerminId)}
                        </select>
                        <div class="form-text">Wählen Sie „Kein Termin (auschecken)“, um den Teilnehmer auszutragen.</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                    </div>
                </form>
            `;

                let historyHtml;

                if (history.length === 0) {
                    historyHtml = '<div class="mt-3">'
                        + '<h6 class="fw-semibold">Einladungshistorie</h6>'
                        + '<p class="text-muted mb-0">Bisher wurden keine Einladungen verschickt.</p>'
                        + '</div>';
                } else {
                    const historyRows = history.map(function (entry, index) {
                        const termin = normalizeValue(entry.termin);
                        const stufe = normalizeValue(entry.stufe);

                        return '<tr>'
                            + '<td class="text-muted">' + (index + 1) + '</td>'
                            + '<td>' + escapeHtml(normalizeValue(entry.eingeladen_am)) + '</td>'
                            + '<td>' + escapeHtml(termin) + '</td>'
                            + '<td>' + escapeHtml(stufe) + '</td>'
                            + '</tr>';
                    }).join('');

                    historyHtml = '<div class="mt-3">'
                        + '<h6 class="fw-semibold">Einladungshistorie</h6>'
                        + '<div class="table-responsive">'
                        + '<table class="table table-sm align-middle mb-0">'
                        + '<thead><tr><th class="text-muted" style="width: 4rem;">#</th><th>Versendet am</th><th>Termin</th><th>Stufe</th></tr></thead>'
                        + '<tbody>' + historyRows + '</tbody>'
                        + '</table>'
                        + '</div>'
                        + '</div>';
                }

                participantModalTitle.textContent = 'Teilnehmerdetails – ' + participantName;
                participantModalBody.innerHTML = '<div class="row g-4">'
                    + '<div class="col-lg-6">' + summaryHtml + '</div>'
                    + '<div class="col-lg-6">' + editFormHtml + '</div>'
                    + '</div>'
                    + historyHtml;

            const modalInstance = bootstrap.Modal.getOrCreateInstance(participantModalElement);
            modalInstance.show();
        }

        participantRows.forEach(function (row) {
            row.addEventListener('dblclick', function (event) {
                if (event.target.closest('a, button, form, input, select, textarea, label')) {
                    return;
                }

                showParticipantModal(row);
            });
        });

        function renderTerminTeilnehmerList(entries) {
            if (!entries || entries.length === 0) {
                return '<p class="text-muted mb-0">Für diesen Termin sind aktuell keine Teilnehmer eingetragen.</p>';
            }

            const rows = entries.map(function (entry) {
                const rawStatus = entry.rueckmeldung_status === undefined || entry.rueckmeldung_status === null
                    ? ''
                    : String(entry.rueckmeldung_status);
                const status = formatRueckmeldung(rawStatus);
                return '<tr>'
                    + `<td>${escapeHtml(entry.name)}</td>`
                    + `<td>${escapeHtml(normalizeValue(entry.email))}</td>`
                    + `<td>${escapeHtml(normalizeValue(entry.handynummer))}</td>`
                    + `<td>${escapeHtml(status)}</td>`
                    + `<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-participant-open="${entry.id}">Details</button></td>`
                    + '</tr>';
            }).join('');

            return '<div class="table-responsive">'
                + '<table class="table table-sm align-middle mb-0">'
                + '<thead><tr><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Status</th><th></th></tr></thead>'
                + `<tbody>${rows}</tbody>`
                + '</table>'
                + '</div>';
        }

        terminRows.forEach(function (row) {
            row.addEventListener('dblclick', function (event) {
                if (event.target.closest('a, button, form, input, select, textarea, label')) {
                    return;
                }

                if (!terminModalElement || !terminModalBody || !terminModalTitle) {
                    return;
                }

                const terminId = row.dataset.terminId;
                const terminZeitpunkt = normalizeValue(row.dataset.terminZeitpunkt);
                const terminStufe = normalizeValue(row.dataset.terminStufe);
                const entries = terminTeilnehmer[terminId] || [];

                terminModalTitle.textContent = `Terminübersicht – ${terminZeitpunkt}`;
                terminModalBody.innerHTML = `
                    <div class="mb-3">
                        <div><strong>Stufe:</strong> ${escapeHtml(terminStufe)}</div>
                        <div><strong>Teilnehmer:</strong> ${entries.length}</div>
                    </div>
                    ${renderTerminTeilnehmerList(entries)}
                `;
                if (terminDeleteButton) {
                    terminDeleteButton.dataset.terminId = terminId;
                }

                terminModalBody.querySelectorAll('[data-participant-open]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const participantId = button.getAttribute('data-participant-open');
                        const participantRow = document.querySelector('[data-participant-row][data-id=\"' + participantId + '\"]');
                        if (participantRow) {
                            showParticipantModal(participantRow);
                        }
                    });
                });

                const modalInstance = bootstrap.Modal.getOrCreateInstance(terminModalElement);
                modalInstance.show();
            });
        });

        // Beim Öffnen des Modals den Teilnehmer-ID in das versteckte Feld schreiben
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var deleteId = button.getAttribute('data-delete-id');
            var modalInput = deleteModal.querySelector('#delete_id');
            modalInput.value = deleteId;
        });

        // Termin-ID in das Löschmodal schreiben
        var deleteTerminModal = document.getElementById('deleteTerminModal');
        if (deleteTerminModal) {
            deleteTerminModal.addEventListener('show.bs.modal', function () {
                var modalInput = deleteTerminModal.querySelector('#termin_delete_id');
                var terminId = terminDeleteButton ? terminDeleteButton.dataset.terminId : '';
                modalInput.value = terminId || '';
            });
        }

        // Session-Meldung nach 3 Sekunden automatisch ausblenden
        var sessionAlert = document.querySelector('[data-session-message]');
        if (sessionAlert) {
            sessionAlert.classList.add('fade');
            if (!sessionAlert.classList.contains('show')) {
                sessionAlert.classList.add('show');
            }

            setTimeout(function () {
                sessionAlert.classList.remove('show');
                sessionAlert.addEventListener('transitionend', function () {
                    sessionAlert.remove();
                }, { once: true });
            }, 3000);
        }
    </script>

</body>
</html>
