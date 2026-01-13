<?php
// Front controller for MVC routing

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

$publicDir = __DIR__;
$request = App\Core\Request::fromGlobals();
$router = new App\Core\Router();

$router->get('/', static function (): void {
    (new App\Controllers\AuthController())->showLogin();
});
$router->get('/login', static function (): void {
    (new App\Controllers\AuthController())->showLogin();
});
$router->post('/login', static function (): void {
    (new App\Controllers\AuthController())->login();
});
$router->get('/login.php', static function (): void {
    (new App\Controllers\AuthController())->showLogin();
});
$router->post('/login.php', static function (): void {
    (new App\Controllers\AuthController())->login();
});
$router->get('/index.php', static function (): void {
    (new App\Controllers\AuthController())->showLogin();
});

$router->get('/postfach', static function (): void {
    (new App\Controllers\PostfachController())->inbox();
});
$router->get('/postfach/compose', static function (): void {
    (new App\Controllers\PostfachController())->compose();
});
$router->post('/postfach/store', static function (): void {
    (new App\Controllers\PostfachController())->store();
});
$router->get('/messages', static function (): void {
    (new App\Controllers\MessageController())->index();
});
$router->get('/messages/{id}', static function (string $id): void {
    (new App\Controllers\MessageController())->show((int) $id);
});
$router->post('/messages/store', static function (): void {
    (new App\Controllers\MessageController())->store();
});
$router->post('/messages/mark-as-read', static function (): void {
    (new App\Controllers\MessageController())->markAsRead();
});
$router->get('/messages/inbox', static function (): void {
    (new App\Controllers\MessageController())->inbox();
});

if ($router->dispatch($request)) {
    return;
}

$path = $request->path();
$trimmedPath = trim($path, '/');

if (str_contains($trimmedPath, '..')) {
    http_response_code(400);
    echo 'Ungültiger Pfad';
    return;
}

$staticCandidate = $publicDir . '/' . $trimmedPath;
if (is_file($staticCandidate) && !str_ends_with($staticCandidate, '.php')) {
    $mime = mime_content_type($staticCandidate) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($staticCandidate);
    return;
}

$viewFile = str_ends_with($trimmedPath, '.php') ? $trimmedPath : $trimmedPath . '.php';
$viewCandidate = $publicDir . '/' . $viewFile;

if (is_file($viewCandidate)) {
    (new App\Controllers\LegacyController($publicDir))->render($viewFile);
    return;
}

http_response_code(404);
echo 'Seite nicht gefunden';
