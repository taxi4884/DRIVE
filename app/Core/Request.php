<?php

namespace App\Core;

class Request
{
    private string $path;
    private string $method;

    public function __construct(string $path, string $method)
    {
        $this->path = $path;
        $this->method = strtoupper($method);
    }

    public static function fromGlobals(): self
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $trimmed = '/' . ltrim($uriPath, '/');
        return new self($trimmed, $_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function method(): string
    {
        return $this->method;
    }
}
