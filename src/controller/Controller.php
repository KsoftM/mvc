<?php

namespace ksoftm\system\controller;

use ReflectionClass;
use ksoftm\system\DB;
use ReflectionMethod;
use ksoftm\system\model\Model;
use ksoftm\system\kernel\Route;
use ksoftm\system\utils\html\BuildMixer;

class Controller
{
    private static ?string $resourcesPath = null;
    private static array $landDirectory = [];

    /**
     * Class constructor.
     */
    public function __construct()
    {
    }

    // TODO controller must be configure
    public static function config(string $resourcesPath, array $landDirectory): void
    {
        self::$resourcesPath = $resourcesPath;
        self::$landDirectory = $landDirectory;
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

    public function view(string $path, array $data = []): bool
    {
        // get the rout path
        BuildMixer::build(
            self::$resourcesPath,
            self::$resourcesPath . "/$path",
            self::$landDirectory,
            $data,
        );

        return false;
    }
}
