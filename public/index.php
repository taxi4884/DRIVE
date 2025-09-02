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

switch ($uri) {
    case '':
    case 'index.php':
    case 'login':
        require __DIR__ . '/login.php';
        break;
    case 'messages':
    case 'messages/inbox':
        (new App\Controllers\MessageController())->inbox();
        break;
    case 'messages/mark-as-read':
        (new App\Controllers\MessageController())->markAsRead();
        break;
    default:
        http_response_code(404);
        echo 'Seite nicht gefunden';
}
