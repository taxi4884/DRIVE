<?php
require_once 'db.php';

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isDriver() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'fahrer';
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
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
