<?php
if (!isset($_SESSION['user_role'])) {
    header("Location: index.php");
    exit();
}

if ($_SESSION['user_role'] === 'fahrer') {
    header("Location: ../driver/dashboard.php");
    exit();
} elseif ($_SESSION['user_role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
} else {
    // Sicherheitsmaßnahmen bei unbekanntem User-Role
    session_destroy();
    header("Location: index.php");
    exit();
}
?>