<?php

// Menu definition
$menuEntries = [
    [
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung'],
        'icon' => 'bi-house',
    ],
    [
        'label' => 'Postfach',
        'url' => 'postfach.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung'],
        'icon' => 'bi-envelope',
    ],
    [
        'label' => 'Fahrbetrieb',
        'roles' => ['Admin', 'Mitarbeiter'],
        'icon' => 'bi-truck',
        'children' => [
            [
                'label' => 'Besetzung',
                'url' => 'fahrzeuge.php',
                'roles' => ['Admin', 'Mitarbeiter'],
                'icon' => 'bi-people',
            ],
            [
                'label' => 'Fahrer',
                'url' => 'fahrer.php',
                'roles' => ['Admin', 'Mitarbeiter'],
                'icon' => 'bi-person-badge',
                'children' => [
                    [
                        'label' => 'Abwesenheit',
                        'url' => 'abwesenheit_fahrer.php',
                        'roles' => ['Admin', 'Mitarbeiter'],
                        'icon' => 'bi-calendar-x',
                    ],
                    [
                        'label' => 'Bußgelder',
                        'url' => 'fines_management.php',
                        'roles' => ['Admin', 'Mitarbeiter'],
                        'icon' => 'bi-exclamation-octagon',
                    ],
                ],
            ],
            [
                'label' => 'Fahrzeuge',
                'url' => 'fahrzeug_overview.php',
                'roles' => ['Admin', 'Mitarbeiter'],
                'icon' => 'bi-truck',
                'children' => [
                    [
                        'label' => 'Fahrzeugübergaben',
                        'url' => 'vehicle_transfer.php',
                        'roles' => ['Admin', 'Mitarbeiter'],
                        'icon' => 'bi-arrow-left-right',
                    ],
                    [
                        'label' => 'Service',
                        'url' => 'service.php',
                        'roles' => ['Admin', 'Mitarbeiter'],
                        'icon' => 'bi-tools',
                    ],
                    [
                        'label' => 'Sauberkeit',
                        'url' => 'sauberkeit.php',
                        'roles' => ['Admin', 'Mitarbeiter'],
                        'icon' => 'bi-droplet',
                    ],
                ],
            ],
        ],
    ],
    [
        'label' => 'Verwaltung',
        'url'   => 'verwaltung_abwesenheit.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Zentrale', 'Abrechnung'],
        'icon'  => 'bi-gear',
    ],
    [
        'label' => 'Abrechnung',
        'roles' => ['Abrechnung'],
        'icon' => 'bi-cash-coin',
        'children' => [
            [
                'label' => 'Umsatzdashboard',
                'url' => 'umsatz_dashboard.php',
                'roles' => ['Abrechnung'],
                'icon' => 'bi-graph-up',
            ],
            [
                'label' => 'Fahrerabrechnung',
                'url' => 'fahrer_umsatz.php',
                'roles' => ['Abrechnung'],
                'icon' => 'bi-receipt',
            ],
            [
                'label' => 'Statistik',
                'url' => 'statistik.php',
                'roles' => ['Abrechnung'],
                'icon' => 'bi-bar-chart',
            ],
            [
                'label' => 'Vergleich',
                'url' => 'fahrer_vergleich.php',
                'roles' => ['Abrechnung'],
                'icon' => 'bi-diagram-3',
            ],
        ],
    ],
    [
        'label' => 'Zentrale',
        'roles' => ['Zentrale'],
        'icon' => 'bi-telephone',
        'children' => [
            [
                'label' => 'Zentralendashboard',
                'url' => 'zentrale_dashboard.php',
                'roles' => ['Zentrale'],
                'icon' => 'bi-speedometer',
            ],
            [
                'label' => 'Dienstplan',
                'url' => 'dienstplan_erstellung.php',
                'roles' => ['Zentrale'],
                'icon' => 'bi-calendar',
            ],
            [
                'label' => 'Schichten',
                'url' => 'shift_control.php',
                'roles' => ['Zentrale'],
                'icon' => 'bi-clock',
            ],
            [
                'label' => 'Mitarbeiter',
                'url' => 'mitarbeiter_management.php',
                'roles' => ['Zentrale'],
                'icon' => 'bi-people',
            ],
        ],
    ],
    [
        'label' => 'Sonstiges',
        'roles' => ['Admin', 'Mitarbeiter'],
        'icon' => 'bi-three-dots',
        'children' => [
            [
                'label' => 'Schulung',
                'url' => 'schulungsverwaltung.php',
                'roles' => ['Admin'],
                'icon' => 'bi-journal-text',
            ],
            [
                'label' => 'XRechnung',
                'url' => 'xrechnung_viewer.php',
                'roles' => ['Admin', 'Mitarbeiter'],
                'icon' => 'bi-file-earmark-text',
            ],
        ],
    ],
    [
        'label' => 'Admin',
        'roles' => ['Admin'],
        'icon' => 'bi-shield-lock',
        'children' => [
            [
                'label' => 'Benutzerverwaltung',
                'url' => 'benutzerverwaltung.php',
                'roles' => ['Admin'],
                'icon' => 'bi-people',
            ],
            [
                'label' => 'Nachrichtenrechte',
                'url' => 'message_permissions.php',
                'roles' => ['Admin'],
                'icon' => 'bi-envelope-lock',
            ],
        ],
    ],
    [
        'label' => 'Logout',
        'url' => 'logout.php',
        'roles' => [],
        'icon' => 'bi-box-arrow-right',
    ],
    // Bottom navigation for drivers
    [
        'label' => 'Persönliches',
        'url' => 'personal.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
        'icon' => 'bi-person',
    ],
    [
        'label' => 'Fahrzeug',
        'url' => 'fahrzeug.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
        'icon' => 'bi-truck',
    ],
    [
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
        'icon' => 'bi-house',
    ],
    [
        'label' => 'Umsatz',
        'url' => 'umsatz_erfassen.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
        'icon' => 'bi-currency-euro',
    ],
    [
        'label' => 'Postfach',
        'url' => 'postfach.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
        'icon' => 'bi-envelope',
    ],
    [
        'label' => 'Logout',
        'url' => 'logout.php',
        'roles' => ['Fahrer'],
        'context' => 'bottom',
        'icon' => 'bi-box-arrow-right',
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
    $html = '<ul class="nav-links">';
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
        $iconHtml = '';
        if (!empty($item['icon'])) {
            $iconClass = htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8');
            $iconHtml = '<i class="bi ' . $iconClass . '"></i> ';
        }
        $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $aClassAttr . '>' . $iconHtml . $label . '</a>';
        if ($hasChildren) {
            $childHtml = buildMenu($item['children'], $userRoles, $currentPath);
            $html .= str_replace('<ul class="nav-links">', '<ul class="dropdown-menu">', $childHtml);
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
    $menuEntries = is_array($menuEntries) ? $menuEntries : [];

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
