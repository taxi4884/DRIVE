<?php
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$menuItems = [
    [
        'href' => 'dashboard.php',
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'Dashboard',
    ],
    [
        'href' => 'personal.php',
        'icon' => 'fas fa-user',
        'label' => 'Persönliches',
    ],
    [
        'href' => 'fahrzeug.php',
        'icon' => 'fas fa-car',
        'label' => 'Fahrzeug',
    ],
    [
        'href' => 'umsatz_erfassen.php',
        'icon' => 'fas fa-euro-sign',
        'label' => 'Umsatz',
    ],
    [
        'href' => 'logout.php',
        'icon' => 'fas fa-sign-out-alt',
        'label' => 'Logout',
    ],
];

$filteredMenuItems = array_values(array_filter($menuItems, function ($item) use ($currentPage) {
    return $currentPage !== $item['href'];
}));

$menuId = 'floating-menu-' . uniqid();
?>

<div class="floating-menu" data-menu>
    <button
        type="button"
        class="floating-menu__toggle"
        aria-expanded="false"
        aria-controls="<?= $menuId ?>"
        aria-label="Menü öffnen"
    >
        <span class="floating-menu__icon" aria-hidden="true"></span>
    </button>

    <ul id="<?= $menuId ?>" class="floating-menu__list" aria-label="Schnellnavigation" aria-hidden="true">
        <?php foreach ($filteredMenuItems as $index => $item): ?>
            <?php
                $isActive = $currentPage === $item['href'];
                $linkClasses = 'floating-menu__link' . ($isActive ? ' floating-menu__link--active' : '');
            ?>
            <li class="floating-menu__item" style="--item-index: <?= $index ?>;">
                <a href="<?= $item['href'] ?>" class="<?= $linkClasses ?>">
                    <span class="floating-menu__link-icon" aria-hidden="true">
                        <i class="<?= $item['icon'] ?>"></i>
                    </span>
                    <span class="floating-menu__label"><?= $item['label'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php include 'nav-script.php'; ?>
