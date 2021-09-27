<?php

namespace ksoftm\system\model;

use ksoftm\system\utils\ArgumentsTrait;

class Model
{
    use ArgumentsTrait;

    final public function __get(string $name)
    {
        if (key_exists($name, $this->getAll())) {
            return $this->getArguments($name, 1);
        }

        return null;
    }

    final public function __set(string $name, string $data)
    {
        $this->setArguments($name, $data, 1, true);
    }

    public function findById(string $id, string $colum = 'id'): void
    {
    }

    private function tableName(): string
    {
        return strtolower(pathinfo($this::class, PATHINFO_FILENAME) . 's');
    }
}
