<?php
require_once '../../includes/navigation.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="floating-nav">
    <?php
    renderMenu(
        $_SESSION['rolle'] ?? '',
        $sekundarRolle ?? '',
        'bottom',
        $currentPage
    );
    ?>
</div>
