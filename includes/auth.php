<?php
require_once 'db.php';

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isDriver() {
    return isLoggedIn() && $_SESSION['user_type'] === 'Fahrer';
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'Benutzer';
}

function redirectToDashboard() {
    if (isDriver()) {
        header("Location: ../driver/dashboard.php");
        exit;
    } elseif (isAdmin()) {
        header("Location: dashboard.php");
        exit;
    }
}
?>
