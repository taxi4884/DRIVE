<?php

// Menu definition
$menuEntries = [
    [
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung'],
    ],
    [
        'label' => 'Besetzung',
        'url' => 'fahrzeuge.php',
        'roles' => ['Admin', 'Mitarbeiter'],
    ],
    [
        'label' => 'Fahrerdashboard',
        'url' => 'fahrer.php',
        'roles' => ['Admin', 'Mitarbeiter'],
        'children' => [
            [
                'label' => 'Abwesenheit',
                'url' => 'abwesenheit_fahrer.php',
                'roles' => ['Admin', 'Mitarbeiter'],
            ],
            [
                'label' => 'Bußgelder',
                'url' => 'fines_management.php',
                'roles' => ['Admin', 'Mitarbeiter'],
            ],
        ],
    ],
    [
        'label' => 'Fahrzeugdashboard',
        'url' => 'fahrzeug_overview.php',
        'roles' => ['Admin', 'Mitarbeiter'],
        'children' => [
            [
                'label' => 'Fahrzeugübergaben',
                'url' => 'vehicle_transfer.php',
                'roles' => ['Admin', 'Mitarbeiter'],
            ],
            [
                'label' => 'Service',
                'url' => 'service.php',
                'roles' => ['Admin', 'Mitarbeiter'],
            ],
            [
                'label' => 'Sauberkeit',
                'url' => 'sauberkeit.php',
                'roles' => ['Admin', 'Mitarbeiter'],
            ],
        ],
    ],
    [
        'label' => 'Zentralendashboard',
        'url' => 'zentrale_dashboard.php',
        'roles' => ['Admin', 'Mitarbeiter'],
    ],
    [
        'label' => 'Schulung',
        'url' => 'schulungsverwaltung.php',
        'roles' => ['Admin'],
    ],
    [
        'label' => 'XRechnung',
        'url' => 'xrechnung_viewer.php',
        'roles' => ['Admin', 'Mitarbeiter'],
    ],
    [
        'label' => 'Abrechnung',
        'roles' => ['Abrechnung'],
        'children' => [
            [
                'label' => 'Umsatzdashboard',
                'url' => 'umsatz_dashboard.php',
                'roles' => ['Abrechnung'],
            ],
            [
                'label' => 'Fahrerabrechnung',
                'url' => 'fahrer_umsatz.php',
                'roles' => ['Abrechnung'],
            ],
            [
                'label' => 'Statistik',
                'url' => 'statistik.php',
                'roles' => ['Abrechnung'],
            ],
            [
                'label' => 'Vergleich',
                'url' => 'fahrer_vergleich.php',
                'roles' => ['Abrechnung'],
            ],
        ],
    ],
    [
        'label' => 'Zentrale',
        'roles' => ['Zentrale'],
        'children' => [
            [
                'label' => 'Dienstplan',
                'url' => 'dienstplan_erstellung.php',
                'roles' => ['Zentrale'],
            ],
            [
                'label' => 'Schichten',
                'url' => 'shift_control.php',
                'roles' => ['Zentrale'],
            ],
            [
                'label' => 'Mitarbeiter',
                'url' => 'mitarbeiter_management.php',
                'roles' => ['Zentrale'],
            ],
        ],
    ],
    [
        'label' => 'Admin',
        'roles' => ['Admin'],
        'children' => [
            [
                'label' => 'Benutzerverwaltung',
                'url' => 'benutzerverwaltung.php',
                'roles' => ['Admin'],
            ],
            [
                'label' => 'Schulung',
                'url' => 'schulungsverwaltung.php',
                'roles' => ['Admin'],
            ],
            [
                'label' => 'Nachrichtenrechte',
                'url' => 'message_permissions.php',
                'roles' => ['Admin'],
            ],
        ],
    ],
    [
        'label' => 'Logout',
        'url' => 'logout.php',
        'roles' => [],
    ],
    // Bottom navigation for drivers
    [
        'label' => 'Persönliches',
        'url' => 'personal.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
    ],
    [
        'label' => 'Fahrzeug',
        'url' => 'fahrzeug.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
    ],
    [
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
    ],
    [
        'label' => 'Umsatz',
        'url' => 'umsatz_erfassen.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
    ],
    [
        'label' => 'Logout',
        'url' => 'logout.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
    ],
];

/**
 * Check if a user has a given secondary role.
 */
function hasRole(string $role, $sekundarRolle): bool
{
    if (is_array($sekundarRolle)) {
        $secondary = $sekundarRolle;
    } elseif (is_string($sekundarRolle)) {
        $secondary = array_filter(array_map('trim', explode(',', $sekundarRolle)));
    } else {
        $secondary = [];
    }

    return in_array($role, $secondary, true);
}

/**
 * Build a menu from the provided items filtering by user roles.
 */
function buildMenu(array $items, array $userRoles, string $currentPath = ''): string
{
    $html = '<ul>';
    foreach ($items as $item) {
        $roles = $item['roles'] ?? [];
        $allowed = empty($roles);
        if (!$allowed) {
            foreach ($roles as $role) {
                if ($userRoles['primary'] === $role || hasRole($role, $userRoles['secondary'])) {
                    $allowed = true;
                    break;
                }
            }
        }
        if (!$allowed) {
            continue;
        }

        $hasChildren = !empty($item['children']);
        $liClass = $hasChildren ? ' class="dropdown"' : '';
        $html .= "<li$liClass>";
        $url = $item['url'] ?? '#';
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        $aClasses = [];
        if ($hasChildren) {
            $aClasses[] = 'dropdown-toggle';
        }
        if ($currentPath !== '' && basename($url) === $currentPath) {
            $aClasses[] = 'active';
        }
        $aClassAttr = $aClasses ? ' class="' . implode(' ', $aClasses) . '"' : '';
        $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $aClassAttr . '>' . $label . '</a>';
        if ($hasChildren) {
            $childHtml = buildMenu($item['children'], $userRoles, $currentPath);
            $html .= str_replace('<ul>', '<ul class="dropdown-menu">', $childHtml);
        }
        $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
}

/**
 * Render navigation menu for the given role context.
 */
function renderMenu($currentRole, $secondaryRoles, $context = 'top', $currentPath = '')
{
    global $menuEntries;

    if (is_string($secondaryRoles)) {
        $secondary = array_filter(array_map('trim', explode(',', $secondaryRoles)));
    } elseif (is_array($secondaryRoles)) {
        $secondary = $secondaryRoles;
    } else {
        $secondary = [];
    }

    $userRoles = [
        'primary' => $currentRole,
        'secondary' => $secondary,
    ];

    $items = array_filter($menuEntries, static function ($item) use ($context) {
        return ($item['context'] ?? 'top') === $context;
    });

    echo '<nav>' . buildMenu($items, $userRoles, $currentPath) . '</nav>';
}

?>
