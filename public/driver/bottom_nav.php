<?php
// Aktuelle Seite ermitteln
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="floating-nav">
    <nav>
        <ul>
            <li>
                <a href="personal.php" class="<?= $currentPage === 'personal.php' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i>
                    Persönliches
                </a>
            </li>
            <li>
                <a href="fahrzeug.php" class="<?= $currentPage === 'fahrzeug.php' ? 'active' : '' ?>">
                    <i class="fas fa-car"></i>
                    Fahrzeug
                </a>
            </li>
            <li>
                <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="umsatz_erfassen.php" class="<?= $currentPage === 'umsatz_erfassen.php' ? 'active' : '' ?>">
                    <i class="fas fa-euro-sign"></i>
                    Umsatz
                </a>
            </li>
            <li>
                <a href="logout.php" class="<?= $currentPage === 'logout.php' ? 'active' : '' ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
</div>
