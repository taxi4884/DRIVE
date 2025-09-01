<?php
// Datenbankverbindung herstellen
$host = 'w01d16ef.kasserver.com';
$db = 'd0420c51'; // Name deiner Datenbank
$user = 'd0420c51'; // Dein MySQL-Benutzername
$password = '6UQQRE2svxdS8HvR59Tn'; // Dein MySQL-Passwort

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}
?>
