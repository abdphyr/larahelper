<?php

namespace Abd\Larahelpers\Traits;


trait BaseServiceActions
{
    public function getList($data)
    {
        return $this->{$this->baseRepositoryName(__FUNCTION__)}->getList($data);
    }

    public function create($data)
    {
        return $this->{$this->baseRepositoryName(__FUNCTION__)}->create($data);
    }

    public function show($id)
    {
        return $this->{$this->baseRepositoryName(__FUNCTION__)}->show($id);
    }

    public function edit($id, $data)
    {
        return $this->{$this->baseRepositoryName(__FUNCTION__)}->edit($id, $data);
    }

    public function delete($id)
    {
        return $this->{$this->baseRepositoryName(__FUNCTION__)}->delete($id);
    }

    public function softDelete($id)
    {
        return $this->{$this->baseRepositoryName(__FUNCTION__)}->softDelete($id);
    }

    private function baseRepositoryName($method)
    {
        $class = explode("\\", static::class);
        $servise = end($class);
        $model = strtolower(str_replace('Service', '', $servise));
        $property = $model . "Repository";
        if (property_exists(static::class, $property)) {
            return $property;
        } else {
            throw new \Error(
                "$" . $property . " not found. Please write correctly repository name or override App\Traits\BaseServiceActions::$method in ". static::class .' or inject repository in ' . static::class . ' constructor'
            );
        }
    }
}
