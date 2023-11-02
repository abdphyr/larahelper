<?php

namespace Abd\Larahelpers\Traits;

use Illuminate\Support\Facades\Schema;

trait ReadModel
{
    /**
     * Eager loading uchun actionda ko'rsatiladiga relationlar.
     * @var array
     */
    public array $relations = [];

    /**
     * Eager loading uchun actionda oddiy php array holatda ko'rsatiladigan va keyin ORM ga parse qilinadigan relationlar.
     * @var array 
     */
    public array $willParseToRelation = [];

    /**
     * Umumiy conditiondan tashqari maxsus conditionlar. Actionda yoziladi.
     * @var array
     */
    public array $conditions = [];

    /**
     * Model uchun default order. Agar requestda sort param berilmasa, shu attribute bo'yicha sort qilinadi.
     * @var array
     */
    public array $defaultOrder = [['column' => 'id', 'direction' => 'asc']];

    /**
     * Modeldagi to'g'ridan to'gri equal filter qilinadigan fieldlar ro'yxati.
     * @var array
     */
    public array $equalableFields = [];

    /**
     * Modeldagi numeric interval filter qilinadigan fieldlar ro'yxati.
     * @var array
     */
    public array $numericIntervalFields = [];


    /**
     * Modeldagi date interval filter qilinadigan fieldlar ro'yxati.
     * @var array
     */
    public array $dateIntervalFields = [];

    /**
     * Modeldagi like filter qilinadigan fieldlar ro'yxati. Translation tabledagi fieldlar bundab mustasno.
     * @var array
     */
    public array $likableFields = [];

    /**
     * Modeldagi relationlar like filter qilinadigan fieldlar ro'yxati. Translation tabledagi fieldlar bundab mustasno.
     * @var array
     */
    public array $relationLikableFields = [];

    /**
     * Modelning translation table idagi like filter qilinadigan fieldlar ro'yxati.
     *
     * @var array
     */
    public array $translationFields = [];


    public function getList($data)
    {
        $needPagination = $data['pagination'] ?? 1;
        $page = $data['page'] ?? 1;
        $rows = $data['rows'] ?? 100;
        $this->authorizeMethod(__FUNCTION__);
        $this->setQuery();
        $this->query->with($this->relations + parseToRelation($this->willParseToRelation));
        $this->query->where($this->prepareConditions());
        $this->callQueryClosure();
        $this->languageFilter();
        $this->filter();
        $this->specialFilter();
        // $this->relationLikableFilter();
        $this->sort();
        $this->specialSort();
        // $this->selector();
        $data = $needPagination ? $this->query->paginate(perPage: $rows, page: $page) : $this->query->get();
        return $this->makeResponse(data: $this->withResource($data, true));
    }

    public function show($id)
    {
        $this->setQuery();
        $this->query->with($this->relations + parseToRelation($this->willParseToRelation));
        $this->callQueryClosure();
        // $this->selector();
        if ($this->withTrashed) $this->query->withTrashed();
        if ($model = $this->findById($id, $this->query)) {
            $this->authorizeMethod(__FUNCTION__, $model);
            return $this->makeResponse(data: $this->withResource($model));
        }
        else return $this->makeResponse(status: 0, message: 'Not found', code: 404);
    }

    public function selector()
    {
        if (!empty($fields = $this->willSelectedFields())) {
            $this->query->selectRaw(implode(', ', $fields));
        }
    }

    protected function willSelectedFields()
    {
        $fields = [];
        if (request('only') or request('except')) {
            $fields = Schema::getColumnListing($this->table);
            $only = explode(",", str_replace(' ', '', request('only') ?? ''));
            $except = explode(",", str_replace(' ', '', request('except') ?? ''));
            foreach ($fields as $key => $field) {
                if (request('only')) {
                    if (!in_array($field, $only)) unset($fields[$key]);
                } else {
                    if (request('except')) {
                        if (in_array($field, $except)) unset($fields[$key]);
                    }
                }
            }
        }
        return $fields;
    }

    protected function callQueryClosure()
    {
        if ($this->checkInitialized('queryClosure')) call_user_func($this->queryClosure, $this->query);
    }

    public function with(array $relations)
    {
        $this->willParseToRelation = $relations;
        return $this;
    }


    public function languageFilter()
    {
        if (!empty($this->translation)) {
            $this->query->whereHas('translation', function ($query) {
                $query->where('language_code', config('app.user_language'));
            });
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

    public function filter()
    {
        // global search
        if ($s = request('s')) {
            $this->query->where(function ($query) use ($s) {
                // relation likeable 
                $this->relationLikableFilter(search: $s, likableRelations: $this->relationLikableFields, query: $query);
                
                // model likable fields
                foreach ($this->likableFields as $field) {
                    $query->orWhere($field, 'ilike', '%' . $s . '%');
                }
                // transaltion likable fields
                if (!empty($this->translation)) {
                    foreach ($this->translationFields as $field) {
                        $query->orWhereHas('translation', function ($query) use ($field, $s) {
                            $query->where($field, 'ilike', '%' . $s . '%');
                        });
                    }
                }
                // relation likable fields
            });
        }
        // model likable filters
        $this->query->where(function ($query) {
            foreach ($this->likableFields as $field) {
                if (!is_null(request($field))) {
                    $query->where($field, 'ilike', '%' . request($field) . '%');
                }
            }
        });
        // translation likable filters
        $this->query->where(function ($query) {
            if (!empty($this->translation)) {
                foreach ($this->translationFields as $field) {
                    if (!is_null(request($field))) {
                        $query->whereHas('translation', function ($query) use ($field) {
                            $query->where($field, 'ilike', '%' . request($field) . '%');
                        });
                    }
                }
            }
        });
        // exact equal filters
        foreach ($this->equalableFields as $field) {
            if (!is_null(request($field))) {
                $this->query->whereIn($field, explode(',', request($field)));
            }
        }
        // numeric interval filters
        foreach ($this->numericIntervalFields as $field) {
            if (!is_null(request($field)) && str_contains(request($field), '|')) {
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
                if ($from == $to) {
                    $this->query->whereDate($field, $from);
                } else {
                    $this->query->where(function ($query) use ($field, $from, $to) {
                        if ($from) $query->whereDate($field, '>=', $from);
                        if ($to) $query->whereDate($field, '<=', $to);
                    });
                }
            } else if (request($field)) {
                $this->query->whereDate($field, request($field));
            }
        }
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
     * Service uchun maxsus sort qo'shish funksiyasi. Service ni o'zida ushbu funksiya overwrite qilinadi.
     *
     * @param null
     * @return Query $query
     */
    public function specialSort()
    {
    }

    /**
     * Service uchun relation columnlariga filter qo'shish funksiyasi. Service ni o'zida ushbu funksiya overwrite qilinadi.
     *
     * @param NULL
     * @return Query $query
     */
    public function relationLikableFilter($search, $likableRelations, $query)
    {
        foreach ($likableRelations as $relation => $field) {
            if (is_array($field)) {
                foreach ($field as $key => $value) {
                    if (is_string($key)) {
                        $this->relationLikableFilter(search: $search, likableRelations: [$key => $value], query: $query);
                    } else {
                        $query->orWhereHas($relation, function ($q) use ($value, $search) {
                            $q->where($value, 'ilike', '%' . $search . '%');
                        });
                    }
                }
            } else if (is_string($field)) {
                $query->orWhereHas($relation, function ($q) use ($field, $search) {
                    $q->where($field, 'ilike', '%' . $search . '%');
                });
            }
        }
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
}
