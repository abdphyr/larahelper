<?php

namespace Abd\Larahelpers\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class SingleService
{

    /**
     * HTTP response.
     *
     * @var mixed
     */

    protected $response;

    /**
     * HTTP status code.
     *
     * @var mixed
     */
    protected $code;

    /**
     * Obect of the model
     *
     * @var mixed
     */
    protected $model;

    /**
     * ...
     *
     * @var mixed
     */
    public $manyRelation;

    /**
     * QUery builder for the model
     *
     * @var Builder
     */
    protected $query;


    /**
     * For using query in anywhere !!!
     * @var ?\Closure
     */
    public ?\Closure $queryClosure = null;

    /**
     * QUery builder for the model
     *
     * @var Builder
     */
    protected $withTrashed = false;

    /**
     * Model Resource class
     *
     * @var JsonResource
     */
    protected $resource, $showResource;

    /**
     * Model Resources
     *
     * @var JsonResource[]
     */
    protected $resources, $showResources;

    /**
     * Model Translation class
     *
     * @var mixed
     */
    protected $translation;

    /**
     * Export file class
     *
     * @var mixed
     */
    protected $exportClass;

    /**
     * Import file class
     *
     * @var mixed
     */
    protected $importClass;

    /**
     * ...
     *
     * @var mixed
     */
    protected $extraColumn;

    /**
     * Eager loading uchun actionda ko'rsatiladiga relationlar.
     *
     * @var array
     */
    public array $relations = [];

    /**
     * Eager loading uchun actionda oddiy php array holatda ko'rsatiladigan va keyin ORM ga parse qilinadigan relationlar.
     *
     * @var array
     */
    public array $willParseToRelation = [];

    /**
     * Umumiy conditiondan tashqari maxsus conditionlar. Actionda yoziladi.
     *
     * @var array
     */
    public array $conditions = [];

    /**
     * Modeldagi like filter qilinadigan fieldlar ro'yxati. Translation tabledagi fieldlar bundab mustasno.
     *
     * @var array
     */
    public array $likableFields = [];

    /**
     * Databasega yozilishidan oldin JSON ga o'tkaziladigan fieldlar ro'yxati
     *
     * @var array
     */
    public array $serializingToJsonFields = [];

    /**
     * Modelning translation table idagi like filter qilinadigan fieldlar ro'yxati.
     *
     * @var array
     */
    public array $translationFields = [];

    /**
     * Modeldagi to'g'ridan to'gri equal filter qilinadigan fieldlar ro'yxati.
     *
     * @var array
     */
    public array $equalableFields = [];

    /**
     * Modeldagi numeric interval filter qilinadigan fieldlar ro'yxati.
     *
     * @var array
     */
    public array $numericIntervalFields = [];


    /**
     * Modeldagi date interval filter qilinadigan fieldlar ro'yxati.
     *
     * @var array
     */
    public array $dateIntervalFields = [];

    /**
     * Model uchun default order. Agar requestda sort param berilmasa, shu attribute bo'yicha sort qilinadi.
     *
     * @var array
     */
    public array $defaultOrder = [['column' => 'id', 'direction' => 'asc']];

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

        if (!empty($this->translation)) {
            $this->relations += ['translations', 'translation'];
        }
        $this->setTableName();
        $this->setReturnData();
    }

    protected function setReturnData(int $status = null, string $message = null, mixed $data = null, int $code = 200): void
    {
        $this->response = [
            'status' => $status ?? 1,
            'message' => $message ?? 'Success',
        ];
        $this->response['data'] = $data;

        $this->code = $code;
    }

    protected function return()
    {
        return [
            'response' => $this->response,
            'code' => $this->code,
        ];
    }

    protected function withResource($data, $isCollection = false)
    {
        if ($this->resource) {
            if ($isCollection) {
                return $this->resource::collection($data);
            } else {
                return $this->showResource ? $this->showResource::make($data) : $this->resource::make($data);
            }
        } else {
            return $data;
        }
    }

    public function generateReturnedData(int $status = null, string $message = null, mixed $data = null, int $code = 200)
    {
        $this->response = [
            'status' => $status ?? 1,
            'message' => $message ?? 'Success',
        ];
        $this->response['data'] = $data;
        $this->code = $code;
        return $this->return();
    }

    public function getList($data)
    {
        $this->authorizeMethod(__FUNCTION__);
        $needPagination = $data['pagination'] ?? 1;
        $page = $data['page'] ?? 1;
        $rows = $data['rows'] ?? 1000;

        $this->setQuery();
        $this->query->with($this->relations + $this->parseToRelation());
        $this->query->where($this->prepareConditions());
        $this->languageFilter();
        $this->filter();
        $this->specialFilter();
        $this->relationLikableFilter();
        $this->sort();
        $this->callQueryClosure();
        $this->specialSort();

        if ($needPagination) {
            $data = $this->query->paginate(perPage: $rows, page: $page);
        } else {
            $data = $this->query->get();
        }

        $this->setReturnData(data: $this->withResource($data, true));
        return $this->return();
    }

    protected function callQueryClosure()
    {
        try {
            if ($this->queryClosure) call_user_func($this->queryClosure, $this->query);
        } catch (\Throwable $th) {
            if ($this->checkInitialized('queryClosure')) call_user_func($this->queryClosure, $this->query);
        }
    }

    protected function prepareConditions()
    {
        if (!empty($this->table)) {
            return array_map(function ($condition) {
                if (!empty($condition)) {
                    $column = $condition[0];
                    if (!str_contains($column, $this->table)) {
                        $condition[0] = "$this->table.$column";
                    }
                }
                return $condition;
            }, $this->conditions);
        } else return $this->conditions;
    }

    protected function checkInitialized($property, $class = null, $object = null)
    {
        return (new \ReflectionProperty($class ?? static::class, $property))->isInitialized($object ?? $this);
    }

    public function create($data)
    {
        $data = $this->checkColumn($data, 'created_by');
        DB::connection($this->model->connection)->beginTransaction();
        $this->authorizeMethod(__FUNCTION__);
        try {

            // save translations
            if (isset($data['translations'])) {
                $translations = $data['translations'];

                unset($data['translations']);
            }

            foreach ($this->serializingToJsonFields as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    $data[$field] = json_encode($data[$field]);
                }
            }
            $model = $this->model->create($data);

            if ($this->translation) {
                foreach ($translations as $translation) {
                    $this->translation::create($translation + ['object_id' => $model->id]);
                }
            }

            DB::connection($this->model->connection)->commit();
            $model->refresh();
            $this->setReturnData(data: $this->withResource($model), code: 201);
        } catch (\Throwable $throwable) {
            DB::connection($this->model->connection)->rollBack();
            Log::error($throwable->getMessage() . " " . static::class . '::' . __FUNCTION__ . ' ' . $throwable->getLine() ?? __LINE__ . ' line');
            $this->setReturnData(status: 0, message: 'not_implemented. ' . $throwable->getMessage(), code: 501);
        }

        return $this->return();
    }

    public function find($values)
    {
        if ($this->query === null) {
            $this->query = $this->model->query();
        }
        if (is_array($values)) {
            foreach ($values as $column => $value) {
                $this->query = $this->query->where($column, $value);
            }
            return $this->query->first();
        }
    }

    public function findByIdOrUiid($id, $query = null)
    {
        if (!$query) $this->setQuery();
        if ($this->model->hasUuid) {
            if ($this->id == 'id') $this->id = 'uuid';
            try {
                $model = $this->query->where($this->id, '=', $id)->first();
            } catch (QueryException $e) {
                $model = null;
            }
        } else {
            $model = $this->query->where($this->id, '=', $id)->first();
        }
        return $model;
    }

    public function edit($id, $data)
    {
        $this->setQuery();
        $model = $this->findByIdOrUiid($id);
        if ($model) {
            $this->authorizeMethod(__FUNCTION__, $model);
            try {
                $translations = [];
                if (isset($data['translations'])) {
                    $translations = $data['translations'];
                    unset($data['translations']);
                }
                $data['updated_by'] = auth()->id();
                foreach ($this->serializingToJsonFields as $field) {
                    if (isset($data[$field]) && is_array($data[$field])) {
                        $data[$field] = json_encode($data[$field]);
                    }
                }
                $model->update($data);

                if ($this->translation) {
                    foreach ($translations as $translation) {
                        if (isset($translation['id']) && !empty($translation['id'])) {
                            $trModel = $this->translation::find($translation['id']);
                            if ($trModel) {
                                $trModel->update($translation);
                            }
                        } else {
                            $isExists = $this->translation::firstWhere([
                                'object_id' => $model->id,
                                'language_code' => $translation['language_code'],
                            ]);
                            if (!$isExists) {
                                $this->translation::create($translation + ['object_id' => $model->id]);
                            }
                        }
                    }
                }
                DB::connection($this->model->connection)->commit();
                $model->refresh();
                $this->setReturnData(data: $this->withResource($model));
            } catch (\Throwable $throwable) {
                DB::connection($this->model->connection)->rollBack();
                Log::error($throwable->getMessage() . " " . static::class . '::' . __FUNCTION__ . ' ' . $throwable->getLine() ?? __LINE__ . ' line');
                $this->setReturnData(status: 0, message: 'not_implemented', code: 501);
            }
        } else {
            $message = 'not_found';
            $arr = explode('\\', $this->model::class);
            if ($this->checkInitialized('model')) $message = Str::lower(Str::snake(end($arr))) . "_$message";
            $this->setReturnData(status: 0, message: $message, code: 404);
        }

        return $this->return();
    }

    public function show($id)
    {
        $this->setQuery();
        $this->query->with($this->relations + $this->parseToRelation());
        $this->callQueryClosure();
        if ($this->withTrashed) $this->query->withTrashed();
        $model = $this->findByIdOrUiid($id, $this->query);
        if ($model) {
            $this->authorizeMethod(__FUNCTION__, $model);
            $this->setReturnData(data: $this->withResource($model));
        } else {
            $message = 'not_found';
            $arr = explode('\\', $this->model::class);
            if ($this->checkInitialized('model')) $message = Str::lower(Str::snake(end($arr))) . "_$message";
            $this->setReturnData(status: 0, message: $message, code: 404);
        }
        return $this->return();
    }

    public function delete($id)
    {
        $this->setQuery();
        $model = $this->findByIdOrUiid($id);
        if ($model) {
            $this->authorizeMethod(__FUNCTION__, $model);
            if ($this->translation) $model->translations()->delete();
            $model->delete();
            $this->setReturnData(code: 204);
        } else {
            $message = 'not_found';
            $arr = explode('\\', $this->model::class);
            if ($this->checkInitialized('model')) $message = Str::lower(Str::snake(end($arr))) . "_$message";
            $this->setReturnData(status: 0, message: $message, code: 404);
        }

        return $this->return();
    }

    public function softDelete($id)
    {
        $this->setQuery();
        $model = $this->findByIdOrUiid($id);
        if ($model) {
            $this->authorizeMethod(__FUNCTION__, $model);
            $model->deleted_by = auth()->id();
            $model->save();
            $model->delete();
            $this->setReturnData(code: 204);
        } else {
            $message = 'not_found';
            $arr = explode('\\', $this->model::class);
            if ($this->checkInitialized('model')) $message = Str::lower(Str::snake(end($arr))) . "_$message";
            $this->setReturnData(status: 0, message: $message, code: 404);
        }

        return $this->return();
    }

    protected function setQuery()
    {
        $this->query = $this->model->query();
    }


    private function checkColumn($data, $column)
    {
        $isColExist = Schema::connection($this->model->connection)->hasColumn($this->model->getTable(), $column);
        if ($isColExist)
            $data[$column] = auth()->id() ?? 1;

        return $data;
    }

    public function languageFilter()
    {
        if (!empty($this->translation)) {
            $this->query->whereHas('translation', function ($query) {
                $query->where('language_code', config('app.user_language'));
            });
        }
    }

    public function filter()
    {
        // global search
        if (request('s') !== null) {
            $this->query->where(function ($query) {

                // model likable fields
                foreach ($this->likableFields as $field) {
                    $query->orWhere($field, 'ilike', '%' . request('s') . '%');
                }
                // transaltion likable fields
                if (!empty($this->translation)) {
                    foreach ($this->translationFields as $field) {
                        $query->orWhereHas('translation', function ($query) use ($field) {
                            $query->where($field, 'ilike', '%' . request('s') . '%');
                        });
                    }
                }

                // relation likable fields
                $query = $this->relationLikableFilter($query);
            });
        }
        // model likable filters
        $this->query->where(function ($query) {
            foreach ($this->likableFields as $field) {
                if (request($field) !== null) {
                    $query->where($field, 'ilike', '%' . request($field) . '%');
                }
            }
        });

        // translation likable filters
        $this->query->where(function ($query) {
            if (!empty($this->translation)) {
                foreach ($this->translationFields as $field) {
                    if (request($field) !== null) {
                        $query->whereHas('translation', function ($query) use ($field) {
                            $query->where($field, 'ilike', '%' . request($field) . '%');
                        });
                    }
                }
            }
        });

        // exact equal filters
        foreach ($this->equalableFields as $field) {
            if (request($field) !== null && request($field) != 'null') {
                $this->query->whereIn($field, explode(',', request($field)));
            }
        }

        // numeric interval filters
        foreach ($this->numericIntervalFields as $field) {
            if (request($field) !== null && str_contains(request($field), '|')) {
                list($from, $to) = explode('|', request($field));
                $this->query->where(function ($query) use ($field, $from, $to) {
                    if ($from) $query->where($field, '>=', $from);
                    if ($to) $query->where($field, '<=', $to);
                });
            }
        }

        // date interval filters
        foreach ($this->dateIntervalFields as $field) {
            if (request($field) && str_contains(request($field), '|')) {
                list($from, $to) = explode('|', request($field));
                $this->query->where(function ($query) use ($field, $from, $to) {
                    if ($from) $query->whereDate($field, '>=', $from);
                    if ($to) $query->whereDate($field, '<=', $to);
                });
            }
        }

        // dd($this->query->toSql(), $this->query->getBindings());

    }

    /**
     * Service uchun maxsus filter qo'shish funksiyasi. Service ni o'zida ushbu funksiya overwrite qilinadi.
     *
     * @param null
     * @return Query $query
     */
    public function specialFilter()
    {
    }

    /**
     * Service uchun relation columnlariga filter qo'shish funksiyasi. Service ni o'zida ushbu funksiya overwrite qilinadi.
     *
     * @param NULL
     * @return Query $query
     */
    public function relationLikableFilter()
    {
        //
    }

    protected function sort()
    {
        if ($sort = request('sort')) {
            foreach (explode(',', $sort) as $s) {
                $desc = str_starts_with($s, '-');
                $type = $desc ? 'DESC' : 'ASC';
                $field = $desc ? substr($s, 1) : $s;
                if ($this->columnExists($field)) {
                    $this->query->orderBy("$this->table.$field", $type);
                }
            }
        } elseif (!empty($this->defaultOrder)) {
            foreach ($this->defaultOrder as $order) {
                $this->query->orderBy($this->table . '.' . $order['column'], $order['direction']);
            }
        }
    }

    private function columnExists($column)
    {
        return Schema::connection($this->model->connection)->hasColumn($this->model->getTable(), $column);
    }

    protected function specialSort()
    {
    }

    public function sync($id, $data): array
    {
        $this->setQuery();
        $relation = $this->manyRelation;
        $model = $this->findByIdOrUiid($id);
        if ($model) {
            $model->$relation()->sync($data);
            $this->setReturnData(data: $this->withResource($model));
        } else {
            $message = 'not_found';
            $arr = explode('\\', $this->model::class);
            if ($this->checkInitialized('model')) $message = Str::lower(Str::snake(end($arr))) . "_$message";
            $this->setReturnData(status: 0, message: $message, code: 404);
        }

        return $this->return();
    }

    public function export()
    {
        try {
            return Excel::download(new $this->exportClass, 'export_' . time() . '.xlsx');

            //            return Excel::store(new $this->exportClass, 'export.xlsx'); // Postman uchun
        } catch (\Exception $e) {
            $this->setReturnData(status: 0, message: $e->getMessage(), code: 500);
            return $this->return();
        }
    }

    public function changeResource(int|string $index)
    {
        if (is_string($index)) {
            $this->resource = $index;
            return $this;
        }
        if (isset($this->resources[$index])) {
            $this->resource = $this->resources[$index];
        }
        return $this;
    }

    public function changeShowResource(int|string $index)
    {
        if (is_string($index)) {
            $this->showResource = $index;
            return $this;
        }
        if (isset($this->showResources[$index])) {
            $this->showResource = $this->showResources[$index];
        }
        return $this;
    }

    public function parseToRelation($relations = null)
    {
        $parsed = [];
        if (is_null($relations)) {
            $relations = $this->willParseToRelation;
        }
        foreach ($relations as $key => $relation) {
            if (is_string($key)) {
                $parsed[$key] = $this->makeWithRelationQuery($relation);
            } else {
                $parsed[] = $relation;
            }
        }
        return $parsed;
    }

    private function makeWithRelationQuery($relation)
    {
        $selects = [];
        $withs = [];
        if (empty($relation)) {
            return function ($q) {
                $q->select('*');
            };
        }
        $order = false;
        foreach ($relation as $key => $value) {
            if ($key === 'order' && is_string($value) && !empty($value)) {
                $order = $value;
                continue;
            }
            if (is_string($key)) {
                $withs[$key] = $this->makeWithRelationQuery($value);
            } else {
                if (str_contains($value, '|')) {
                    $selects[] = explode("|", $value)[0];
                    $order = $value;
                } else {
                    $selects[] = $value;
                }
            }
        }
        return function ($q) use ($selects, $withs, $order) {
            if ($order) {
                $sort = explode('|', $order);
                $use = isset($sort[1]) && (strtolower($sort[1]) === 'asc' || strtolower($sort[1]) === 'desc');
                $q->orderBy($sort[0], $use ? strtolower($sort[1]) : 'asc');
            }
            if (empty($selects)) {
                $q->select('*');
            } else {
                $q->selectRaw(implode(',', $selects));
            }
            if (!empty($withs)) {
                $q->with($withs);
            }
        };
    }

    /**
     * Table name of the model
     * @var string
     */
    public string $table;

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
        } else $this->table = '';
        return $this->table;
    }
}
