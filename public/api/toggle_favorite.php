<?php
// public/api/toggle_favorite.php
require_once __DIR__ . '/../../includes/head.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$userId  = (int) $_SESSION['user_id'];
$menuUrl = $_POST['menu_url'] ?? '';

if ($menuUrl === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'menu_url fehlt']);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB-Verbindung fehlt']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM user_menu_favorites WHERE user_id = :uid AND menu_url = :url');
    $stmt->execute(['uid' => $userId, 'url' => $menuUrl]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $del = $pdo->prepare('DELETE FROM user_menu_favorites WHERE id = :id');
        $del->execute(['id' => $existing['id']]);
        echo json_encode(['success' => true, 'favorite' => false]);
    } else {
        $ins = $pdo->prepare('INSERT INTO user_menu_favorites (user_id, menu_url) VALUES (:uid, :url)');
        $ins->execute(['uid' => $userId, 'url' => $menuUrl]);
        echo json_encode(['success' => true, 'favorite' => true]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
