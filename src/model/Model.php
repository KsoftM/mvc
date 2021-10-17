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

    public function viewableFields(): array|false
    {
        $res = array_merge($this->graded ?: $this->fillable, $this->hidden);
        foreach ($res as $key => $value) {
            if (in_array($value, $this->hidden)) {
                unset($res[$key]);
            }
        }
        ksort($res);
        return $res;
    }

    public function toArray(): array|false
    {
        return $this->args ?: false;
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

    public function validate(): array
    {
        $rules = [];

        $this->fillable = [];

        foreach ($this->rules() as $value) {
            if ($value instanceof MegRule) {
                // assign the fillable field with name
                $field = $value->getField(false);

                if ($field != false) {
                    $this->fillable[] = $field;
                }

                // generate the rules for tha field and values
                $rules[] = [$this->args->getValue($field), $value];
            }
        }

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
     * @param string|int $data
     *
     * @return boolean
     */
    public function findAndLoad(string|int $data): bool
    {
        $result = $this->findByPrimaryKey($data);

        foreach ($result as $value) {
            if ($value instanceof DResult) {
                $result = $value;
                break;
            }
        }

        if (($result instanceof DResult) === false) {
            $result = $this->findByUniqueKeys($data);

            foreach ($result as $value) {
                if ($value instanceof DResult) {
                    $result = $value;
                    break;
                }
            }
        }

        if ($result instanceof DResult) {
            return $this->loadData($result);
        } else {
            return false;
        }
    }

    /**
     * find data by primary keys and load if needed
     *
     * @param string|int $data
     *
     * @return array|false
     */
    protected function findByPrimaryKey(string|int $data): array|false
    {
        foreach ($this->primaryKeys ?: [] as $key) {
            $res = $this->find([$key => $data]);
            if ($res instanceof DResult) {
                return $res;
            }
        }

        return false;
    }

    /**
     * find data by unique keys and load if needed
     *
     * @param string|int $data
     *
     * @return array|false
     */
    protected function findByUniqueKeys(string|int $data): array|false
    {
        foreach ($this->uniqueKeys ?: [] as $key) {
            $res = $this->find([$key => $data]);
            if ($res instanceof DResult) {
                return $res;
            }
        }

        return false;
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
        if ($result->rowCount() === 1) {
            foreach ($result->getData()[0] as $key => $value) {
                $this->$key = $value;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * find the model using associative array
     *
     * @param array $data
     *
     * @return array|false
     */
    private function find(array $data): array|false
    {
        if (!empty($fields) && !empty($data)) {
            $result = [];
            foreach ($data ?: [] as $key => $value) {
                $result[] = DB::select(
                    $this->tableName(),
                    fn (Query $q) => $q
                        ->where(
                            $this->conditionField([$key]),
                            $value
                        )
                );
            }
            return $result ?: false;
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

    public function keyCheck(): bool
    {
        $primaryKeys = [];
        $uniqueKeys = [];

        array_map(function (string $key) use (&$set) {
            $set[$key] = $this->args->getValue($key);
        }, $this->primaryKeys);

        array_map(function (string $key) use (&$set) {
            $set[$key] = $this->args->getValue($key);
        }, $this->uniqueKeys);

        if (!empty($primaryKeys) && !empty($uniqueKeys)) {
            if ($this->isValidKey($primaryKeys) && $this->isValidKey($uniqueKeys)) {
                return true;
            }
        }
        return false;
    }

    private function isValidKey(array $keys): bool
    {
        foreach ($keys as $value) {
            $res = DB::select(
                $this->tableName(),
                fn (Query $q) => $q->where(
                    $this->conditionField($value),
                    $this->conditionData($value)
                )
            );


            if ($res instanceof DResult && $res->rowCount() > 0) {
                return false;
            }
        }

        return true;
    }

    public function insert(): DResult|false
    {
        $set = [];

        if ($this->keyCheck() == false) {
            return false;
        }

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

    public function first(int $count = 0, int $offset = 0): DResult|false
    {
        return DB::select(
            $this->tableName(),
            $count > 0
                ? fn (Query $q) => $q->field($this->viewableFields())->limit($count, $offset)
                : fn (Query $q) => $q->field($this->viewableFields())
        );
    }

    protected function tableName(): string
    {
        return strtolower(pathinfo($this::class, PATHINFO_FILENAME) . 's');
    }
}
