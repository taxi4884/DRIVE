<?php
require_once 'auth.php'; // Prüft, ob der Benutzer eingeloggt ist
require_once 'db.php';   // Stellt die Datenbankverbindung bereit

// Seiten, bei denen das require_once nicht erfolgen soll
$excludedPages = ['login.php', 'register.php'];

// Aktuellen Dateinamen abrufen
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// Prüfen, ob die Seite nicht in den Ausnahmen ist
if (!in_array($currentPage, $excludedPages)) {
    require_once 'user_check.php';
}
?>
