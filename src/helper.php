<?php

use ksoftm\system\controller\Controller;
use ksoftm\system\utils\View;

if (!function_exists('view')) {
    function view(string $path, array $data): void
    {
        $p = new View();
        $p->view($path, $data);
    }
}
