<?php
require_once 'auth.php'; // Prüft, ob der Benutzer eingeloggt ist
require_once 'cache.php';
require 'db.php';   // Stellt die Datenbankverbindung bereit

// Seiten, bei denen das require_once nicht erfolgen soll
$excludedPaths = ['/', '/login', '/login.php', '/index.php', '/register.php'];

// Aktuellen Pfad abrufen
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

// Prüfen, ob die Seite nicht in den Ausnahmen ist
if (!in_array($requestPath, $excludedPaths, true)) {
    require_once 'user_check.php';
}
?>
