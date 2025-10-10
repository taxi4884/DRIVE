<?php
require_once '../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (isDriver()) {
    $stmt = $pdo->prepare(
        'SELECT b.BenutzerID AS id, b.Name
         FROM message_permissions mp
         JOIN Benutzer b ON b.BenutzerID = mp.recipient_id
         WHERE mp.driver_id = ?
         ORDER BY b.Name'
    );
    $stmt->execute([$userId]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query('SELECT FahrerID AS id, CONCAT(Vorname, " ", Nachname) AS Name FROM Fahrer WHERE Aktiv = 1 ORDER BY Nachname, Vorname');
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($recipients);
