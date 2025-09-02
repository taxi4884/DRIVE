<?php
// Simple front controller

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($uri === '' || $uri === 'index.php' || $uri === 'login') {
    require __DIR__ . '/login.php';
} elseif ($uri === 'messages') {
    (new App\Controllers\MessageController())->index();
} elseif (preg_match('#^messages/([0-9]+)$#', $uri, $matches)) {
    (new App\Controllers\MessageController())->show((int)$matches[1]);
} elseif ($uri === 'messages/mark-as-read') {
    (new App\Controllers\MessageController())->markAsRead();
} elseif ($uri === 'messages/inbox') {
    (new App\Controllers\MessageController())->inbox();
} else {
    http_response_code(404);
    echo 'Seite nicht gefunden';
}
