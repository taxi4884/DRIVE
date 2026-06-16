<?php

// Menu definition
$abrechnungBaseChildren = [
    [
        'label' => 'Umsatzdashboard',
        'url' => 'umsatz_dashboard.php',
        'roles' => [],
        'icon' => 'bi-graph-up',
    ],
    [
        'label' => 'Fahrerabrechnung',
        'url' => 'fahrer_umsatz.php',
        'roles' => [],
        'icon' => 'bi-receipt',
    ],
    [
        'label' => 'Statistik',
        'url' => 'statistik.php',
        'roles' => [],
        'icon' => 'bi-bar-chart',
    ],
    [
        'label' => 'Vergleich',
        'url' => 'fahrer_vergleich.php',
        'roles' => [],
        'icon' => 'bi-diagram-3',
    ],
];

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
        'only_user_id' => 1,
        'icon'  => 'bi-gear',
    ],
    [
        'label' => 'Abrechnung',
        'roles' => [],
        'icon' => 'bi-cash-coin',
        'children' => $abrechnungBaseChildren,
        'split_by_company' => true,
    ],
    [
        'label' => 'Zentrale',
        'roles' => ['Zentrale', 'Admin', 'Mitarbeiter'],
        'only_user_id' => 1,
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
                'roles' => ['Zentrale', 'Admin', 'Mitarbeiter'],
                'icon' => 'bi-calendar',
            ],
            [
                'label' => 'Schichten',
                'url' => 'shift_control.php',
                'roles' => ['Zentrale', 'Admin', 'Mitarbeiter'],
                'icon' => 'bi-clock',
            ],
            [
                'label' => 'Mitarbeiter',
                'url' => 'mitarbeiter_management.php',
                'roles' => ['Zentrale', 'Admin', 'Mitarbeiter'],
                'icon' => 'bi-people',
            ],
        ],
    ],
    [
        'label' => 'Sonstiges',
        'roles' => ['Admin', 'Mitarbeiter'],
        'only_user_id' => 1,
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
function buildMenu(array $items, array $userRoles, string $currentPath = '', array $favorites = []): string
{
    $html = '<ul class="nav-links">';
    foreach ($items as $item) {
        if (isset($item['only_user_id'])) {
            $currentUserId = $_SESSION['user_id'] ?? null;
            if ((string) $currentUserId !== (string) $item['only_user_id']) {
                continue;
            }
        }

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

        $url   = $item['url']   ?? '#';
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
        global $unreadMessageCount;
        if (!empty($unreadMessageCount) && isset($item['url']) && basename($item['url']) === 'postfach.php') {
            $badgeHtml = '<span class="badge">' . (int) $unreadMessageCount . '</span>';
        }

        // --- NEU: Favoriten-Status bestimmen ---
        $isFavorite   = isset($item['url']) && in_array($item['url'], $favorites, true);
        $favoriteChar = $isFavorite ? '★' : '☆';

        $favoriteHtml = '';
        if (isset($item['url']) && !empty($_SESSION['user_id'])) {
            $favoriteHtml =
                '<button type="button" class="favorite-toggle" ' .
                'data-menu-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
                $favoriteChar .
                '</button>';
        }

        // Link + Stern rendern
        $html .=
            '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $aClassAttr . '>' .
            $iconHtml . $label . $badgeHtml .
            '</a>' .
            $favoriteHtml;

        if ($hasChildren) {
            $childHtml = buildMenu($item['children'], $userRoles, $currentPath, $favorites);
            $html     .= str_replace('<ul class="nav-links">', '<ul class="dropdown-menu">', $childHtml);
        }
        $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
}

function collectMenuItemsByUrl(array $items, array &$map): void
{
    foreach ($items as $item) {
        if (!empty($item['url'])) {
            $map[$item['url']] = [
                'label' => $item['label'] ?? $item['url'],
                'url' => $item['url'],
                'roles' => $item['roles'] ?? [],
                'icon' => $item['icon'] ?? null,
            ];
        }

        if (!empty($item['children'])) {
            collectMenuItemsByUrl($item['children'], $map);
        }
    }
}

function buildFavoritesMenuChildren(array $favorites, array $menuEntries): array
{
    $map = [];
    collectMenuItemsByUrl($menuEntries, $map);

    $children = [];
    foreach ($favorites as $favoriteUrl) {
        if (!is_string($favoriteUrl)) {
            continue;
        }

        $favoriteUrl = trim($favoriteUrl);
        if ($favoriteUrl === '') {
            continue;
        }

        if (isset($map[$favoriteUrl])) {
            $children[] = $map[$favoriteUrl];
            continue;
        }

        $children[] = [
            'label' => $favoriteUrl,
            'url' => $favoriteUrl,
            'roles' => [],
            'icon' => 'bi-star',
        ];
    }

    if (empty($children)) {
        $children[] = [
            'label' => 'Keine Favoriten',
            'url' => null,
            'roles' => [],
            'icon' => 'bi-star',
        ];
    }

    return $children;
}

function insertFavoritesMenu(array $menuEntries, array $favoritesChildren): array
{
    $favoritesMenu = [
        'label' => 'Favoriten',
        'url' => null,
        'roles' => [],
        'icon' => 'bi-star',
        'children' => $favoritesChildren,
    ];

    $inserted = false;
    foreach ($menuEntries as $index => $item) {
        if (($item['label'] ?? null) === 'Postfach') {
            array_splice($menuEntries, $index + 1, 0, [$favoritesMenu]);
            $inserted = true;
            break;
        }
    }

    if (!$inserted) {
        array_unshift($menuEntries, $favoritesMenu);
    }

    return $menuEntries;
}

/**
 * Render navigation menu for the given role context.
 */
function renderMenu($currentRole, $secondaryRoles, $context = 'top', $currentPath = '', array $favorites = [])
{
    global $menuEntries;
    global $abrechnungBaseChildren;
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

    $menuEntries = applyCompanySplitMenus($menuEntries, $abrechnungBaseChildren);

    if ($context === 'top') {
        $favoritesChildren = buildFavoritesMenuChildren($favorites, $menuEntries);
        $menuEntries = insertFavoritesMenu($menuEntries, $favoritesChildren);
    }

    $items = array_filter($menuEntries, static function ($item) use ($context) {
        return ($item['context'] ?? 'top') === $context;
    });

    echo '<nav>' . buildMenu($items, $userRoles, $currentPath, $favorites) . '</nav>';
}

function applyCompanySplitMenus(array $menuEntries, array $abrechnungBaseChildren): array
{
    $companies = fetchCompaniesForMenu();

    if (empty($companies)) {
        return $menuEntries;
    }

    foreach ($menuEntries as $index => $item) {
        if (!empty($item['split_by_company'])) {
            $menuEntries[$index]['children'] = buildCompanyMenuChildren($abrechnungBaseChildren, $companies);
        }
    }

    return $menuEntries;
}

function fetchCompaniesForMenu(): array
{
    if (empty($_SESSION['user_id'])) {
        return [];
    }

    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        return [];
    }

    $pdo = $GLOBALS['pdo'];

    try {
        $stmt = $pdo->query('SELECT id, name FROM companies ORDER BY name');
        $companies = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }

    if (empty($companies)) {
        return [];
    }

    return array_values(array_filter($companies, static function ($company) {
        return isset($company['id'], $company['name']);
    }));
}

function buildCompanyMenuChildren(array $baseChildren, array $companies): array
{
    $children = [];

    foreach ($companies as $company) {
        $companyId = (int) $company['id'];
        $children[] = [
            'label' => $company['name'],
            'roles' => [],
            'icon' => 'bi-building',
            'children' => appendCompanyIdToMenu($baseChildren, $companyId),
        ];
    }

    return $children;
}

function appendCompanyIdToMenu(array $items, int $companyId): array
{
    $updated = [];

    foreach ($items as $item) {
        $updatedItem = $item;
        if (!empty($updatedItem['url'])) {
            $updatedItem['url'] = appendCompanyIdToUrl($updatedItem['url'], $companyId);
        }
        if (!empty($updatedItem['children'])) {
            $updatedItem['children'] = appendCompanyIdToMenu($updatedItem['children'], $companyId);
        }
        $updated[] = $updatedItem;
    }

    return $updated;
}

function appendCompanyIdToUrl(string $url, int $companyId): string
{
    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . http_build_query(['company_id' => $companyId]);
}


?>
