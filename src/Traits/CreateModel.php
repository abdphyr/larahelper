<?php

namespace Abd\Larahelpers\Traits;

use Error;
use Illuminate\Support\Facades\DB;

trait CreateModel
{
    public function create($data)
    {
        $data = $this->checkColumn($data, 'created_by');
        DB::connection($this->model->connection)->beginTransaction();
        $this->authorizeMethod(__FUNCTION__);
        try {
            list($data, $translations) = $this->popTranslations($data);
            $data = $this->serializeToJson($data);
            $model = $this->model->create($data);
            if ($this->translation) $this->createTranslations($model, $translations);                
            DB::connection($this->model->connection)->commit();
            $model->refresh();
            return $this->makeResponse(data: $this->withResource($model), code: 201);
        } catch (\Throwable $throwable) {
            DB::connection($this->model->connection)->rollBack();
            return $this->makeResponse(status: 0, message: 'Not implemented. ' . $throwable->getMessage(), code: 501);
        }
    }
    
    public function createWithThrow($data)
    {
        $data = $this->checkColumn($data, 'created_by');
        $this->authorizeMethod(__FUNCTION__);
        try {
            list($data, $translations) = $this->popTranslations($data);
            $data = $this->serializeToJson($data);
            $model = $this->model->create($data);
            if ($this->translation) $this->createTranslations($model, $translations); 
            $model->refresh();
            return $model;
        } catch (\Throwable $throwable) {
            throw new Error('Not implemented. ' . $throwable->getMessage(), 501);
        }
    }
}
