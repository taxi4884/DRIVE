<?php
// Central layout to include common head and navigation
include __DIR__ . '/../public/head.php';

// Allow pages to disable the navigation bar by setting $showNav = false
$showNav = $showNav ?? true;
?>
<body>
<?php if ($showNav) { include __DIR__ . '/../public/nav.php'; } ?>
