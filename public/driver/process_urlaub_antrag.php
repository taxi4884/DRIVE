<?php
session_start();
require_once '../../includes/db.php'; // Passe den Pfad an, falls nötig.
require_once __DIR__ . '/error_handler.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$respond = function (bool $success, string $message = '', array $extra = [], ?Throwable $throwable = null) use ($isAjax) {
    if ($throwable instanceof Throwable) {
        driver_log_exception($throwable);
        $message = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra));
        exit;
    }

    if ($success) {
        header('Location: personal.php?success=1');
        exit;
    }

    if ($throwable instanceof Throwable) {
        driver_render_error_page();
        exit;
    }

    $message = $message !== '' ? $message : 'Die Anfrage konnte nicht verarbeitet werden.';
    http_response_code(400);
    echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 'Ungültige Anfrage.');
}

if (!isset($_SESSION['user_id'])) {
    $respond(false, 'Nicht autorisiert.');
}

$fahrer_id = $_SESSION['user_id'];
$startdatum = $_POST['startdatum'] ?? null;
$enddatum = $_POST['enddatum'] ?? null;
$kommentar = trim($_POST['kommentar'] ?? '');

if (!$startdatum || !$enddatum) {
    $respond(false, 'Bitte Start- und Enddatum angeben.');
}

try {
    $start = new DateTime($startdatum);
    $ende = new DateTime($enddatum);
    if ($ende < $start) {
        $respond(false, 'Das Enddatum muss nach dem Startdatum liegen.');
    }
} catch (Exception $e) {
    $respond(false, 'Ungültiges Datum angegeben.');
}

try {
    $insertQuery = "
        INSERT INTO FahrerAbwesenheiten
        (FahrerID, abwesenheitsart, grund, status, startdatum, enddatum, kommentar, erstellt_am, aktualisiert_am)
        VALUES (?, 'Urlaub', 'Urlaub', 'beantragt', ?, ?, ?, NOW(), NOW())
    ";
    $stmt = $pdo->prepare($insertQuery);
    $stmt->execute([$fahrer_id, $startdatum, $enddatum, $kommentar]);

    $respond(true, 'Antrag gespeichert.', [
        'status' => 'beantragt',
        'startdatum' => $startdatum,
        'enddatum' => $enddatum,
    ]);
} catch (PDOException $e) {
    $respond(false, '', [], $e);
}
?>
