<?php

namespace ksoftm\system\controller;

use ReflectionClass;
use ksoftm\system\model\Model;
use ksoftm\system\kernel\Route;
use ksoftm\system\utils\html\BuildMixer;
use ksoftm\system\utils\View;

class Controller
{
    /**
     * Class constructor.
     */
    public function __construct()
    {
    }

    /**
     * specified model for the pedicular controller with user routing data
     *
     * @return \ksoftm\system\model\Model|false
     */
    public function GetModel(): Model|false
    {
        $model = trim(
            str_replace(
                pathinfo(Controller::class, PATHINFO_FILENAME),
                '',
                pathinfo($this::class, PATHINFO_FILENAME)
            )
        );

        $c = new ReflectionClass($model);
        $c = $c->getName();
        $model = new $c();
        if ($model instanceof Model) {
            $id = $model->getPrimaryKey();
            if (!empty($id)) {
                $data = Route::resolve()->getUserPathData();

                if (is_array($data) && array_key_exists($id, $data)) {
                    $data = $data[$id];

                    $model->findAndLoad([$data]);
                    $model->$id = $data;

                    return $model;
                }
            }
        }

        return false;
    }

    public function view(string $path, array $data = []): string
    {
        if (ob_get_length() != false) {
            ob_clean();
        }

        $p = new View();
        return $p->view($path, $data);
    }
}
