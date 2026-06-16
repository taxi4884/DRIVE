<?php

$complaintsToCheckCount = 0;
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    try {
        $complaintsToCheckCount = (int)$GLOBALS['pdo']->query("SELECT COUNT(*) FROM complaints WHERE status='pruefen'")->fetchColumn();
    } catch (Throwable $e) {
        $complaintsToCheckCount = 0;
    }
}

$projectManagementAllowed = false;
if (isset($_SESSION['user_id']) && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    try {
        $stmt = $GLOBALS['pdo']->prepare("SHOW COLUMNS FROM Benutzer LIKE 'ProjectmanagementFreigabe'");
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $GLOBALS['pdo']->prepare("SELECT ProjectmanagementFreigabe FROM Benutzer WHERE BenutzerID = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $projectManagementAllowed = ((int)$stmt->fetchColumn() === 1);
        }
    } catch (Throwable $e) {
        $projectManagementAllowed = false;
    }
}

// Menu definition (nur Top-Navigation, Bottom-Nav entfernt)
$menuEntries = [
    [
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung'],
        'icon' => 'bi-house',
    ],
    [
        'label' => 'Profil',
        'url' => 'profil.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung'],
        'icon' => 'bi-person-circle',
    ],
    [
        'label' => 'Postfach',
        'url' => 'postfach.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung'],
        'icon' => 'bi-envelope',
    ],
    [
        'label' => 'Beschwerden',
        'url' => 'complaints_management.php',
        'roles' => ['Admin', 'Mitarbeiter', 'Fahrer', 'Zentrale', 'Abrechnung', 'Verwaltung'],
        'icon' => 'bi-chat-left-text',
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
        'roles' => ['Admin', 'Mitarbeiter', 'Zentrale', 'Abrechnung', 'Verwaltung'],
        'icon'  => 'bi-gear',
        'children' => [
            [
                'label' => 'Abwesenheit',
                'url'   => 'verwaltung_abwesenheit.php',
                'roles' => ['Admin', 'Mitarbeiter', 'Zentrale', 'Abrechnung', 'Verwaltung'],
                'icon'  => 'bi-calendar-minus',
            ],
            [
                'label' => 'Zeiterfassung prüfen',
                'url'   => 'verwaltung_zeiterfassung.php',
                'roles' => ['Admin', 'Mitarbeiter', 'Zentrale', 'Abrechnung', 'Verwaltung'],
                'icon'  => 'bi-clock-history',
            ],
        ],
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
        'roles' => ['Zentrale', 'Admin', 'Mitarbeiter'],
        'icon' => 'bi-telephone',
        'children' => [
            [
                'label' => 'Zentralendashboard',
                'url' => 'zentrale_dashboard.php',
                'roles' => ['Zentrale', 'Admin', 'Mitarbeiter'],
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
                'roles' => [],
                'icon' => 'bi-journal-text',
            ],
            [
                'label' => 'XRechnung',
                'url' => 'xrechnung_viewer.php',
                'roles' => ['Admin', 'Mitarbeiter'],
                'icon' => 'bi-file-earmark-text',
            ],
            [
                'label' => 'Projektmanagement',
                'url' => 'project_management.php',
                'roles' => [],
                'icon' => 'bi-kanban',
                'visible' => $projectManagementAllowed,
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
 * Find a menu item by URL (rekursiv).
 */
function findMenuItemByUrl(array $items, string $url): ?array
{
    foreach ($items as $item) {
        if (isset($item['url']) && $item['url'] === $url) {
            return $item;
        }
        if (!empty($item['children'])) {
            $found = findMenuItemByUrl($item['children'], $url);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * Build a menu from the provided items filtering by user roles.
 */
function buildMenu(
    array $items,
    array $userRoles,
    string $currentPath = '',
    array $favorites = [],
    int $level = 0
): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $html = '<ul class="nav-links">';
    foreach ($items as $item) {
        $roles = $item['roles'] ?? [];
        $allowed = empty($roles);
        if (array_key_exists('visible', $item) && $item['visible'] === false) {
            $allowed = false;
        }
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

        $url   = $item['url']   ?? '#';

        if ($url === 'verwaltung_zeiterfassung.php' && (int)($_SESSION['zeitpruefung_freigabe'] ?? 0) !== 1) {
            continue;
        }
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

        $badgeHtml = '';
        global $unreadMessageCount, $complaintsToCheckCount;
        if (!empty($unreadMessageCount) && isset($item['url']) && basename($item['url']) === 'postfach.php') {
            $badgeHtml = '<span class="badge">' . (int) $unreadMessageCount . '</span>';
        }
        if (!empty($complaintsToCheckCount) && isset($item['url']) && basename($item['url']) === 'complaints_management.php') {
            $badgeHtml = '<span class="badge">' . (int) $complaintsToCheckCount . '</span>';
        }

        // Nur in Submenüs Sterne anzeigen (level >= 1) und nur bei echten URLs
        $showFavorite = $level >= 1 && isset($item['url']) && $item['url'] !== '#';

        $favoriteHtml = '';
        if ($showFavorite && !empty($_SESSION['user_id'])) {
            $isFavorite   = in_array($item['url'], $favorites, true);
            $favoriteChar = $isFavorite ? '★' : '☆';

            // Stern inline im Link
            $favoriteHtml =
                '<span class="favorite-toggle ms-1" ' .
                'style="color:#ffc107; cursor:pointer;" ' .
                'data-menu-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
                $favoriteChar .
                '</span>';
        }

        // Link + (optional) Stern
        $html .=
            '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $aClassAttr . '>' .
            $iconHtml . $label . $badgeHtml . $favoriteHtml .
            '</a>';

        if ($hasChildren) {
            $childHtml = buildMenu($item['children'], $userRoles, $currentPath, $favorites, $level + 1);
            $html     .= str_replace('<ul class="nav-links">', '<ul class="dropdown-menu">', $childHtml);
        }
        $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
}

/**
 * Render navigation menu for the given role context.
 *
 * $favorites = Array von URLs (z.B. ['dashboard.php', 'fahrer.php'])
 */
function renderMenu($currentRole, $secondaryRoles, $context = 'top', $currentPath = '', array $favorites = [])
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
        'primary'   => $currentRole,
        'secondary' => $secondary,
    ];

    $items = array_filter($menuEntries, static function ($item) use ($context) {
        return ($item['context'] ?? 'top') === $context;
    });

    // Favoriten-Oberpunkt dynamisch einfügen, wenn es welche gibt
    if ($context === 'top' && !empty($favorites)) {
        $favoriteChildren = [];

        foreach ($favorites as $favUrl) {
            $orig = findMenuItemByUrl($menuEntries, $favUrl);
            if ($orig === null) {
                continue;
            }

            $favoriteChildren[] = [
                'label' => $orig['label'],
                'url'   => $orig['url'],
                'roles' => $orig['roles'] ?? [],
                'icon'  => $orig['icon'] ?? null,
            ];
        }

        if (!empty($favoriteChildren)) {
            array_unshift($items, [
                'label' => 'Favoriten',
                'roles' => [$currentRole],
                'icon'  => 'bi-star',
                'children' => $favoriteChildren,
            ]);
        }
    }

    echo '<nav>' . buildMenu($items, $userRoles, $currentPath, $favorites, 0) . '</nav>';
}

?>
