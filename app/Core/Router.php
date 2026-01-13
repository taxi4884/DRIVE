<?php

namespace App\Core;

class Router
{
    /** @var array<string, array<int, array{pattern: string, regex: string, handler: callable}>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function dispatch(Request $request): bool
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = array_values(array_filter(
                $matches,
                static fn ($key): bool => is_int($key),
                ARRAY_FILTER_USE_KEY
            ));
            array_shift($params);

            call_user_func_array($route['handler'], $params);
            return true;
        }

        return false;
    }

    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $normalized = '/' . trim($pattern, '/');
        $regex = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $normalized);
        $regex = '#^' . ($regex === '/' ? '' : $regex) . '/?$#';

        $this->routes[$method][] = [
            'pattern' => $normalized,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }
}
