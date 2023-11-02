<?php

namespace Abd\Larahelpers\Repositories;

use Abd\Larahelpers\Traits\CreateModel;
use Abd\Larahelpers\Traits\DeleteModel;
use Abd\Larahelpers\Traits\WorkTranslations;
use Abd\Larahelpers\Traits\UpdateModel;
use Abd\Larahelpers\Traits\ReadModel;
use Abd\Larahelpers\Traits\ResolveResponse;
use Abd\Larahelpers\Traits\SerializationFields;
use Abd\Larahelpers\Traits\WorkResources;
use Closure;
use Error;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Illuminate\Support\Str;


abstract class BaseRepository
{
    use CreateModel, ReadModel, UpdateModel, DeleteModel;
    use SerializationFields, WorkTranslations, WorkResources;
    use ResolveResponse;

    /**
     * Object of the model
     * @var mixed
     */
    protected $model;

    /**
     * Table name of the model
     * @var string
     */
    public string $table;

    /**
     * ...
     * @var mixed
     */
    public $manyRelation;

    /**
     * Query builder for the model
     * @var Builder
     */
    protected $query;
   
    /**
     * For using query in anywhere !!!
     * @var \Closure
     */
    public Closure $queryClosure;

    /**
     * QUery builder for the model
     * @var Builder
     */
    protected $withTrashed = false;

    /**
     * Export file class
     * @var mixed
     */
    protected $exportClass;

    /**
     * Import file class
     * @var mixed
     */
    protected $importClass;

    /**
     * ...
     * @var mixed
     */
    protected $extraColumn;


    /**
     * O'chirish, yangilash va bitta ma'lumotni o'qish qaysi field orqali amalga oshirilishi(main operation column)
     * @var string
     */
    protected $id = 'id';

    /**
     * Qaysi methodlar aftorizatsiya qilinishi kerakligini aytish
     * @var array
     */
    public array $authorizeMethods = [];

    public bool $needAuthorization = true;

    protected function authorizeMethod($method, $model = null)
    {
        if ($this->needAuthorization) {
            if (is_null($model) && in_array($method, $this->authorizeMethods)) {
                Gate::authorize($method, $this->model);
            } elseif (in_array($method, $this->authorizeMethods)) {
                Gate::authorize($method, $model);
            }
        }
        $this->needAuthorization = true;
    }

    public function __construct()
    {
        if (isset($this->defaultAuthorizeMethods)) {
            $this->authorizeMethods = array_merge($this->authorizeMethods, $this->defaultAuthorizeMethods);
        }
        $this->translation = $this->model->translationClass ?? null;
        if (!empty($this->translation)) $this->relations += ['translations', 'translation'];
        $this->setResponseData();
        $this->setTableName();
    }

    protected function setQuery()
    {
        return $this->query = $this->model->query();
    }

    private function checkColumn($data, $column)
    {
        $isColExist = Schema::connection($this->model->connection)->hasColumn($this->model->getTable(), $column);
        if ($isColExist) $data[$column] = auth()->id() ?? 1;
        return $data;
    }

    private function columnExists($column)
    {
        return Schema::connection($this->model->connection)->hasColumn($this->model->getTable(), $column);
    }

    public function find($values)
    {
        $this->setQuery();
        if (is_array($values)) {
            foreach ($values as $c => $v) $this->query = $this->query->where($c, $v);
            return $this->query->first();
        }
    }

    public function findBy($column, $value)
    {
        return $this->setQuery()->where($column, $value)->first();
    }
    
    public function findByName($name)
    {
        return $this->setQuery()->where('name', $name)->first();
    }

    public function findById($id, $query = null)
    {
        if(!$query) $this->setQuery();
        // if ($this->model->hasUuid) $this->id = 'uuid';
        try {
            return $this->query->where($this->id, '=', $id)->first();
        } catch (QueryException $e) {
            throw new Error("Please give main operation column name " . static::class . '::$id constructor. Default column is id');
        }
    }

    public function exists($id)
    {
        try {
            return $this->setQuery()->where($this->id, '=', $id)->exists();
        } catch (QueryException $e) {
            throw new Error("Please give main operation column name " . static::class . '::$id constructor. Default column is id');
        }
    }

    public function existsByColumn($column, $value)
    {
        try {
            return $this->setQuery()->where($column, '=', $value)->exists();
        } catch (QueryException $e) {
            throw new Error($e->getMessage(), $e->getCode() ?? 501);
        }
    }

    protected function checkInitialized($property, $class = null, $object = null)
    {
        return (new ReflectionProperty($class ?? static::class, $property))->isInitialized($object ?? $this);
    }

    public function sync($id, $data): array
    {
        if ($model = $this->findById($id)) {
            $relation = $this->manyRelation;
            $model->$relation()->sync($data);
            return $this->makeResponse(data: $this->withResource($model));
        } else {
            return $this->makeResponse(status: 0, message: 'Not found', code: 404);
        }
    }

    public function setTableName()
    {
        if ($this->model) {
            if (!is_null($this->model->table)) {
                $this->table = $this->model->table;
            } else {
                $namespaceToArray = explode("\\", get_class($this->model));
                $nameOfTheModel = end($namespaceToArray);
                $this->table = Str::snake(Str::plural($nameOfTheModel));
            };
        }
        return $this->table;
    }
}
