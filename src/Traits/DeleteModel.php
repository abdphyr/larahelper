<?php

namespace Abd\Larahelpers\Traits;

trait DeleteModel
{
    public function delete($id)
    {
        if ($model = $this->findById($id)) {
            $this->authorizeMethod(__FUNCTION__, $model);
            if ($this->translation) $model->translations()->delete();
            $model->delete();
            return $this->makeResponse(code: 204);
        } else return $this->makeResponse(status: 0, message: 'Not found', code: 404);
    }

    public function softDelete($id)
    {
        if ($model = $this->findById($id)) {
            $this->authorizeMethod(__FUNCTION__, $model);
            $model->deleted_by = auth()->id();
            $model->save();
            $model->delete();
            return $this->makeResponse(code: 204);
        } else return $this->makeResponse(status: 0, message: 'Not found', code: 404);
    }
}
