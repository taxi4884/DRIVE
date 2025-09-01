<?php
// Beispiel: Menüeinträge basierend auf der Sekundarrolle
$menuEntries = [
    'Abrechnung' => '<div class="dropdown">
                        <a href="umsatz_dashboard.php" class="dropdown-toggle">Umsatzdashboard</a>
                        <div class="dropdown-menu">
                            <a href="fahrer_umsatz.php">Fahrerabrechnung</a>
							<a href="statistik.php">Statistik</a>
							<a href="fahrer_vergleich.php">Vergleich</a>
                        </div>
                    </div>',
    'Zentrale' => '<div class="dropdown">
                        <a class="dropdown-toggle">Zentralenadministration</a>
                        <div class="dropdown-menu">
                            <a href="dienstplan_erstellung.php">Dienstplan</a>
                            <a href="shift_control.php">Schichten</a>
                            <a href="mitarbeiter_management.php">Mitarbeiter</a>
                        </div>
                    </div>',
    'Admin' => '<div class="dropdown">
                    <a class="dropdown-toggle">Verwaltung</a>
                    <div class="dropdown-menu">
                        <a href="benutzerverwaltung.php">Benutzerverwaltung</a>
                        <a href="schulungsverwaltung.php">Schulung</a>
                    </div>
                </div>',
];

function hasRole($role, $sekundarRolle) {
    // Explodiert die SET-Werte und prüft, ob die Rolle enthalten ist
    $roles = explode(',', $sekundarRolle);

    // Wenn die gesuchte Rolle "Admin" ist, zusätzlich die primäre Rolle prüfen
    if ($role === 'Admin' && $_SESSION['rolle'] === 'Admin') {
        return true;
    }

    // Standardprüfung: Sekundärrolle durchsuchen
    return in_array($role, $roles);
}

// Anzahl ungelesener Krankmeldungen ermitteln
if (!isset($_SESSION['user_id'])) {
    $unreadCount = 0;
} else {
    $userId = (int)$_SESSION['user_id'];
    try {
        // Anzahl ungelesener Krankmeldungen
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM abwesenheiten_zentrale az
            JOIN abwesenheiten_read_status ars
              ON az.abwesenheit_id = ars.abwesenheit_id
            WHERE ars.BenutzerID = :user_id
              AND ars.read_status = 0
              AND az.typ = 'Krank'
        ");
        $stmt->execute(['user_id' => $userId]);
        $unreadCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $unreadCount = 0;
    }
}

// Neue Abfrage: Anzahl beantragter Urlaube
try {
    $urlaubStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM FahrerAbwesenheiten
        WHERE abwesenheitsart = 'Urlaub' 
          AND status = 'beantragt'
    ");
    $urlaubStmt->execute();
    $beantragteUrlaubCount = (int)$urlaubStmt->fetchColumn();
} catch (PDOException $e) {
    $beantragteUrlaubCount = 0;
}

// Abfrage für Anzahl der Abwesenheiten des aktuellen Fahrers
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM FahrerAbwesenheiten
        WHERE FahrerID = :fahrer_id
    ");
    $stmt->execute(['fahrer_id' => $_SESSION['user_id']]);
    $abwesenheitCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $abwesenheitCount = 0;
}
?>

<nav>
    <div class="nav-logo">
        <img src="images/4884-logo.png" alt="Logo" />
    </div>
    <button class="burger-menu" aria-label="Menu">
        &#9776;
    </button>

    <div class="nav-links">
        <?php 
        $isAdmin = (isset($_SESSION['rolle']) && $_SESSION['rolle'] === 'Admin');
        ?>

        <?php if ($isAdmin): ?>
            <!-- Admin-Navigation -->
            <div class="dropdown">
                <a class="dropdown-toggle" href="dashboard.php">Dashboard</a>
                <div class="dropdown-menu">
                    <a href="fahrzeuge.php">Besetzung</a>
                    <div class="dropdown">
                        <a class="dropdown-toggle" href="fahrer.php">Fahrerdashboard</a>
                        <?php if (!empty($beantragteUrlaubCount)): ?>
                            <span class="badge"><?= $beantragteUrlaubCount ?></span>
                        <?php endif; ?>
                        <div class="dropdown-menu">
                            <a href="abwesenheit_fahrer.php">Abwesenheit</a>
							<a href="fines_management.php">Bußgelder</a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <a class="dropdown-toggle" href="fahrzeug_overview.php">Fahrzeugdashboard</a>
                        <div class="dropdown-menu">
                            <a href="vehicle_transfer.php">Fahrzeugübergaben</a>
                            <a href="service.php">Service</a>
                            <a href="sauberkeit.php">Sauberkeit</a>
                        </div>
                    </div>
                    <a href="zentrale_dashboard.php">
                        Zentralendashboard
                        <?php if (!empty($unreadCount)): ?>
                            <span class="badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Nicht-Admin-Navigation -->
			<a href="dashboard.php">Dashboard</a>
            <a href="fahrzeuge.php">Besetzung</a>
            <div class="dropdown">
                <a class="dropdown-toggle" href="fahrer.php">Fahrerdashboard</a>
                <?php if (!empty($beantragteUrlaubCount)): ?>
                    <span class="badge"><?= $beantragteUrlaubCount ?></span>
                <?php endif; ?>
                <div class="dropdown-menu">
                    <a href="abwesenheit_fahrer.php">Abwesenheit</a>
					<a href="fines_management.php">Bußgelder</a>
                </div>
            </div>
            <div class="dropdown">
                <a class="dropdown-toggle" href="fahrzeug_overview.php">Fahrzeugdashboard</a>
                <div class="dropdown-menu">
                    <a href="vehicle_transfer.php">Fahrzeugübergaben</a>
                    <a href="service.php">Service</a>
                    <a href="sauberkeit.php">Sauberkeit</a>
                </div>
            </div>
            <a href="zentrale_dashboard.php">
                Zentralendashboard
                <?php if (!empty($unreadCount)): ?>
                    <span class="badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
			<a href="schulungsverwaltung.php">Schulung</a>
			<a href="xrechnung_viewer.php">XRechnung</a>

        <!-- Dynamische Menüeinträge basierend auf Sekundarrollen -->
        <?php foreach ($menuEntries as $role => $menuEntry): ?>
            <?php if (hasRole($role, $sekundarRolle)): ?>
                <?= $menuEntry ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <a href="logout.php">Logout</a>
    </div>
</nav>