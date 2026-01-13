<?php

namespace App\Controllers;

use App\Core\Controller;

class LegacyController extends Controller
{
    public function __construct(string $viewBasePath)
    {
        parent::__construct($viewBasePath);
    }

    public function render(string $viewFile, array $data = []): void
    {
        $this->view->render($viewFile, $data);
    }
}
