<?php

namespace Abd\Larahelpers\Traits;

use Error;
use Illuminate\Support\Facades\DB;

trait UpdateModel
{
    public function edit($id, $data)
    {
        if ($model = $this->findById($id)) {
            try {
                $this->authorizeMethod(__FUNCTION__, $model);
                list($data, $translations) = $this->popTranslations($data);
                $data['updated_by'] = auth()->id();
                $data = $this->serializeToJson($data);
                $model->update($data);
                if ($this->translation) $this->updateTranslations($model, $translations);
                DB::connection($this->model->connection)->commit();
                $model->refresh();
                $this->setResponseData(data: $this->withResource($model));
            } catch (\Throwable $throwable) {
                DB::connection($this->model->connection)->rollBack();
                $this->setResponseData(status: 0, message: 'Not implemented. ' . $throwable->getMessage(), code: 501);
            }
        } else {
            $this->setResponseData(status: 0, message: 'Not found', code: 404);
        }
        return $this->response();
    }

    public function editWithThrow($id, $data)
    {
        if ($model = $this->findById($id)) {
            try {
                $this->authorizeMethod(__FUNCTION__, $model);
                list($data, $translations) = $this->popTranslations($data);
                $data['updated_by'] = auth()->id();
                $data = $this->serializeToJson($data);
                $model->update($data);
                if ($this->translation) $this->updateTranslations($model, $translations);
                $model->refresh();
                return $model;
            } catch (\Throwable $throwable) {
                throw new Error('Not implemented. ' . $throwable->getMessage(), 501);
            }
        } else return null;
    }
}
