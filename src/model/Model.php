<?php

namespace ksoftm\system\model;

use ksoftm\system\DB;
use ksoftm\system\database\Query;
use ksoftm\system\internal\DResult;
use ksoftm\system\utils\validator\MegaValid;
use ksoftm\system\utils\datatype\Dictionary;
use ksoftm\system\utils\validator\MegRule;

abstract class Model
{
    /** @var ListData $args arguments data. */
    protected Dictionary $args;



    /**
     * primary key of the columns to load and find the data
     *
     * @var array
     */
    protected array $primaryKeys = [];

    /**
     * unique key of the columns to load and find the data
     *
     * @var array
     */
    protected array $uniqueKeys = [];

    /**
     * all important fields for the query to fill
     *
     * @var array
     */
    protected array $fillable = [];

    /**
     * given data field for public access
     *
     * @var array
     */
    protected array $graded = [];

    /**
     * hidden field for the public access
     *
     * @var array
     */
    protected array $hidden = [];

    /**
     * Class constructor.
     *
     * @param array|null $data
     */
    public function __construct()
    {
        $this->args = new Dictionary();
    }

    /**
     * public getter
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if ($this->args->haveKey($name)) {
            return $this->args->getValue($name);
        }
        return null;
    }


    /**
     * public setter
     *
     * @param string $name
     * @param string $data
     */
    public function __set(string $name, string $data)
    {
        $this->args->add($name, $data);
    }

    public abstract function rules(): array;

    protected function validate(): array
    {
        $rules = [];

        $this->fillable = [];

        array_map(function (MegRule $val) use (&$rules) {
            // assign the fillable field with name
            $field = $this->fillable[] = $val->getField(false);;

            // generate the rules for tha field and values
            $rules[] = [$this->args->getValue($field), $val];
        }, $this->rules());

        // validate the rules and return the result and can get the processed error messages
        $validate = MegaValid::validate($rules, $errors);

        return [$validate, $errors];
    }

    public function getErrors(): array
    {
        return $this->validate()[1];
    }

    public function isValid(): bool
    {
        return $this->validate()[0];
    }

    /**
     * find and load data using primary key or unique key you specified
     *
     * @param array $data
     *
     * @return boolean
     */
    public function findAndLoad(array $data): bool
    {
        /**
         * @var DResult
         */
        $result = $this->findByPrimaryKey($data);

        if ($result == false) {
            /**
             * @var DResult
             */
            $result = $this->findByUniqueKeys($data);
        }

        if ($result instanceof DResult && $result->rowCount() === 1) {
            return $this->loadData($result);
        } else {
            return false;
        }
    }

    /**
     * find data by primary keys and load if needed
     *
     * @param string $id
     * @param string $column
     *
     * @return void
     */
    protected function findByPrimaryKey(array $keys): DResult|false
    {
        return $this->find($this->primaryKeys, $keys);
    }

    /**
     * find data by unique keys and load if needed
     *
     * @param string $id
     * @param string $column
     *
     * @return void
     */
    protected function findByUniqueKeys(array $keys): DResult|false
    {
        return $this->find($this->uniqueKeys, $keys);
    }

    /**
     * load data using DResult
     *
     * @param \ksoftm\system\internal\DResult $result
     *
     * @return boolean
     */
    protected function loadData(DResult $result): bool
    {
        if ($result->rowCount() == 1) {
            foreach ($result->getData()[0] as $key => $value) {
                $this->$key = $value;
            }
            return true;
        } else {
            return false;
        }
    }

    private function find(array $fields, array $data): DResult|false
    {
        if (!empty($fields) && !empty($data)) {
            $result = DB::select(
                $this->tableName(),
                fn (Query $q) => $q->where(
                    $this->conditionField($fields),
                    $data
                )
            );
            return $result;
        }
        return false;
    }

    public function getPrimaryKey(): ?string
    {
        return $this->primaryKeys[0] ?? null;
    }

    private function conditionField(array $fields): string
    {
        $condition = [];
        foreach ($fields as $key) {
            $condition[] = "$key = ?";
        }

        return implode(' and ', $condition);
    }

    private function conditionData(array $fields): array
    {
        $data = [];
        foreach ($fields as $key) {
            $data[] = $this->args->getValue($key);
        }

        return $data;
    }

    public function insert(): DResult|false
    {
        $set = [];

        array_map(function (string $key) use (&$set) {
            $set[$key] = $this->args->getValue($key);
        }, $this->fillable);

        if ($this->isValid() == true) {
            return DB::insert(
                $this->tableName(),
                fn (Query $q) => $q->field($this->fillable)->set($set)
            );
        }

        return false;
    }

    public function update(): DResult|false
    {
        $set = [];

        array_map(function (string $key) use (&$set) {
            $set[$key] = $this->args->getValue($key);
        }, $this->fillable);

        if ($this->isValid() == true) {
            return DB::delete(
                $this->tableName(),
                fn (Query $q) => $q
                    ->set($set)
                    ->where(
                        $this->conditionField($this->primaryKeys),
                        $this->conditionData($this->primaryKeys)
                    )
            );
        }

        return false;
    }

    public function delete(): DResult|false
    {
        $set = [];

        $this->args->getEach(function ($key, $val) use (&$set) {
            $set[$key] = $val[1];
        });

        if ($this->isValid() == true) {
            return DB::update(
                $this->tableName(),
                fn (Query $q) => $q
                    ->field($this->fillable)
                    ->set($set)
                    ->where(
                        $this->conditionField($this->primaryKeys),
                        $this->conditionData($this->primaryKeys)
                    )
            );
        }

        return false;
    }

    public function first(int $count = 1, int $offset = 0): DResult|false
    {
        return DB::select(
            $this->tableName(),
            fn (Query $q) => $q->limit($count, $offset)
        );
    }

    protected function tableName(): string
    {
        return strtolower(pathinfo($this::class, PATHINFO_FILENAME) . 's');
    }
}
