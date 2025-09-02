<?php
require_once '../includes/bootstrap.php'; 

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    echo "not_authenticated";
    exit;
}

// Benutzer-ID in Variable übernehmen
$userId = (int)$_SESSION['user_id'];

// Abwesenheit-ID aus GET lesen
if (isset($_GET['abwesenheit_id'])) {
    $abwesenheitId = (int)$_GET['abwesenheit_id'];

    try {
        // Upsert in abwesenheiten_read_status
        $stmt = $pdo->prepare("
            INSERT INTO abwesenheiten_read_status (abwesenheit_id, BenutzerID, read_status)
            VALUES (:abwesenheit_id, :user_id, 1)
            ON DUPLICATE KEY UPDATE
                read_status = 1
        ");

        // Hier können wir in :user_id den Wert aus $userId einsetzen,
        // obwohl die DB-Spalte 'BenutzerID' heißt
        $stmt->execute([
            'abwesenheit_id' => $abwesenheitId,
            'user_id'        => $userId
        ]);

        echo "success";
    } catch (PDOException $e) {
        echo "error: " . $e->getMessage();
    }
} else {
    echo "no_id";
}
