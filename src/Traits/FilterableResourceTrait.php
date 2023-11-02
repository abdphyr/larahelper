<?php

namespace Abd\Larahelpers\Traits;

trait FilterableResourceTrait
{
    public static function make(...$parameters)
    {
        $resource = new static(...$parameters);
        $array = $resource->toArray(request());
        $drops = static::willDropedFields(array_keys($array));
        foreach ($drops as $d) unset($array[$d]);
        if (!empty($drops)) return ['data' => $array];
        return $resource;
    }

    public static function collection($resource)
    {
        $resourceCollection = static::newCollection($resource);
        $response = tap($resourceCollection, function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
        $response->collection = $response->collection->map(function ($item) {
            $array = $item->toArray(request());
            $drops = static::willDropedFields(array_keys($array));
            foreach ($drops as $d) unset($array[$d]);
            return empty($drops) ? $item : collect($array);
        });
        return $response;
    }

    protected static function willDropedFields($fields)
    {
        $drops = [];
        if (request('only') or request('except')) {
            $only = explode(",", str_replace(' ', '', request('only') ?? ''));
            $except = explode(",", str_replace(' ', '', request('except') ?? ''));
            foreach ($fields as $key => $field) {
                if (request('only')) {
                    if (!in_array($field, $only)) $drops[] = $fields[$key];
                } else {
                    if (request('except')) {
                        if (in_array($field, $except)) $drops[] = $fields[$key];
                    }
                }
            }
        }
        return $drops;
    }
}
