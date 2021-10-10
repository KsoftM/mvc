<?php

use ksoftm\system\controller\Controller;

if (!function_exists('view')) {
    function view(string $path, array $data): void
    {
        $p = new Controller();
        $p->view($path, $data);
    }
}
