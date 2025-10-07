<?php

if (!function_exists('driverHasRole')) {
    /**
     * Prüft, ob der aktuell eingeloggte Benutzer über die angegebene Rolle verfügt.
     */
    function driverHasRole(string $role): bool
    {
        $normalizedRole = strtolower($role);
        $primaryRole = strtolower($_SESSION['user_role'] ?? '');

        if ($primaryRole === $normalizedRole) {
            return true;
        }

        $secondaryRoles = array_map('strtolower', $_SESSION['sekundarRolle'] ?? []);
        return in_array($normalizedRole, $secondaryRoles, true);
    }
}

if (!function_exists('ensureDriverAccess')) {
    /**
     * Stellt sicher, dass der aktuelle Benutzer als Fahrer angemeldet ist.
     * Bei fehlender Berechtigung erfolgt eine Weiterleitung zur Login- bzw. Startseite.
     */
    function ensureDriverAccess(): void
    {
        $_SESSION['rolle'] = 'Fahrer';

        if (!isset($_SESSION['user_id'])) {
            header('Location: ../login.php');
            exit();
        }

        if (!driverHasRole('fahrer')) {
            header('Location: ../index.php');
            exit();
        }
    }
}

if (!function_exists('requireDriverId')) {
    /**
     * Gibt die Fahrer-ID des aktuell eingeloggten Benutzers zurück und stellt sicher,
     * dass der Benutzer über die Fahrer-Rolle verfügt.
     */
    function requireDriverId(): int
    {
        ensureDriverAccess();
        return (int) $_SESSION['user_id'];
    }
}

if (!function_exists('fetchDriverProfile')) {
    /**
     * Lädt das Fahrer-Profil aus der Datenbank.
     *
     * @throws RuntimeException Wenn kein Fahrerprofil gefunden wurde.
     */
    function fetchDriverProfile(PDO $pdo, ?int $fahrerId = null): array
    {
        $fahrerId = $fahrerId ?? requireDriverId();

        $stmt = $pdo->prepare('SELECT * FROM Fahrer WHERE FahrerID = ?');
        $stmt->execute([$fahrerId]);
        $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fahrer) {
            throw new RuntimeException('Fahrerprofil wurde nicht gefunden.');
        }

        return $fahrer;
    }
}
