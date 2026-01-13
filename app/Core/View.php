<?php

namespace App\Core;

class View
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function render(string $template, array $data = []): void
    {
        $safeTemplate = ltrim($template, '/');
        $path = $this->basePath . '/' . $safeTemplate;

        if (!file_exists($path)) {
            http_response_code(404);
            echo 'View nicht gefunden';
            return;
        }

        extract($data, EXTR_SKIP);
        require $path;
    }
}
