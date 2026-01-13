<?php

namespace App\Core;

class Controller
{
    protected View $view;

    public function __construct(string $viewBasePath)
    {
        $this->view = new View($viewBasePath);
    }
}
