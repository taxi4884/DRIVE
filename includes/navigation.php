<?php

// Menu definition
$MENU_ITEMS = [
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

function renderMenu($currentRole, $secondaryRoles, $context = 'top')
{
    global $MENU_ITEMS;

    // Normalize secondary roles to array
    if (is_string($secondaryRoles)) {
        $secondary = array_filter(array_map('trim', explode(',', $secondaryRoles)));
    } elseif (is_array($secondaryRoles)) {
        $secondary = $secondaryRoles;
    } else {
        $secondary = [];
    }

    $hasRole = function(array $roles) use ($currentRole, $secondary) {
        if (empty($roles)) {
            return true;
        }
        if (!empty($currentRole) && in_array($currentRole, $roles, true)) {
            return true;
        }
        foreach ($secondary as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }
        return false;
    };

    $renderItems = function(array $items) use (&$renderItems, $hasRole, $context) {
        echo '<ul>';
        foreach ($items as $item) {
            $itemContext = $item['context'] ?? 'top';
            if ($itemContext !== $context) {
                continue;
            }
            $roles = $item['roles'] ?? [];
            if (!$hasRole($roles)) {
                continue;
            }
            $hasChildren = !empty($item['children']);
            $liClass = $hasChildren ? ' class="dropdown"' : '';
            echo "<li$liClass>";
            $url = $item['url'] ?? '#';
            $label = htmlspecialchars($item['label']);
            $aClass = $hasChildren ? ' class="dropdown-toggle"' : '';
            echo "<a href=\"$url\"$aClass>$label</a>";
            if ($hasChildren) {
                echo '<ul class="dropdown-menu">';
                $renderItems($item['children']);
                echo '</ul>';
            }
            echo '</li>';
        }
        echo '</ul>';
    };

    echo '<nav>';
    $renderItems($MENU_ITEMS);
    echo '</nav>';
}

?>
