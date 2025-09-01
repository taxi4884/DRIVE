<?php
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../includes/db.php';

// .env-Datei laden
function loadEnv($pfad) {
    if (!file_exists($pfad)) return [];
    $zeilen = file($pfad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($zeilen as $zeile) {
        $zeile = trim($zeile);
        if (str_starts_with($zeile, '#')) continue;
        if (strpos($zeile, '=') !== false) {
            [$key, $value] = explode('=', $zeile, 2);
            $env[trim($key)] = trim($value);
        }
    }
    return $env;
}

$env = loadEnv(__DIR__ . '/../../includes/.env');
$api_key = $env['API_SECRET_KEY'] ?? null;
$api_url = 'https://funkschulung.4884.de/api/get_schulungsstatus.php';

if (!$api_key) {
    $_SESSION['message'] = '❌ API-Key konnte nicht geladen werden.';
    header("Location: ../schulungsverwaltung.php");
    exit;
}

// Teilnehmer laden
$stmt = $pdo->query("SELECT id, email FROM schulungsteilnehmer WHERE email IS NOT NULL");
$teilnehmer = $stmt->fetchAll(PDO::FETCH_ASSOC);

$erfolge = 0;
$fehler = 0;
$ignoriert = 0;
$meldungen = [];

foreach ($teilnehmer as $t) {
    $email = $t['email'];
    $interne_id = $t['id'];

    $postdata = json_encode([
        'api_key' => $api_key,
        'email'   => $email
    ]);

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $postdata
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($api_url, false, $context);

    if ($response === false) {
        $status_line = $http_response_header[0] ?? 'unbekannter Fehler';
        $fehler++;
        $meldungen[] = "❌ Fehler bei Anfrage für $email – $status_line";
        continue;
    }

    $result = json_decode($response, true);

    if ($result['status'] === 'success') {
        $bestanden = $result['abschlusstest_bestanden'] ? 1 : 0;
        $thema_id  = $result['letztes_thema_id'] ?? null;
        $prozent   = $result['abschluss_prozent'] ?? null;

        $update = $pdo->prepare("
            UPDATE schulungsteilnehmer
            SET abschlusstest_bestanden = ?, abschluss_prozent = ?, letzter_themen_id = ?
            WHERE id = ?
        ");
        $update->execute([$bestanden, $prozent, $thema_id, $interne_id]);

        $erfolge++;
        $meldungen[] = "✅ $email aktualisiert (Bestanden: $bestanden, Thema: $thema_id, $prozent%)";
    } else {
        $error = $result['error'] ?? 'Unbekannter Fehler';

        // Teilnehmer nicht gefunden → ignorieren
        if (str_contains(strtolower($error), 'teilnehmer nicht gefunden')) {
            $ignoriert++;
            continue;
        }

        $fehler++;
        $meldungen[] = "⚠️ Fehler bei $email: $error";
    }
}

// Rückmeldung zusammenbauen
$_SESSION['message'] .= "<h5><strong>Statusabfrage abgeschlossen</strong></h5>";

if ($erfolge > 0) {
    $_SESSION['message'] .= "<ul class='mb-0'>";
    foreach ($meldungen as $m) {
        if (str_starts_with($m, '✅')) {
            $_SESSION['message'] .= "<li class='text-success'>$m</li>";
        }
    }
    $_SESSION['message'] .= "</ul>";
} else {
    $_SESSION['message'] .= "<p class='mb-0 text-muted'>Keine erfolgreichen Rückmeldungen.</p>";
}

header("Location: ../schulungsverwaltung.php");
exit;
