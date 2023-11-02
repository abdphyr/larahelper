<?php

namespace Abd\Larahelpers\Enums;

abstract class BaseEnum
{
    public abstract static function list($withText = false);

    public static function getLabel($code)
    {
        $labels = static::list(true);
        if (isset($labels[$code])) {
            return $labels[$code];
        }
        return null;
    }

    public static function addLabel($types, $key = 'name', $value = 'type')
    {
        if(is_null($types)){
            return [];
        }
        $list = static::list(true);
        try {
            if (is_string($types)) {
                $types = json_decode($types);
            }
            return array_map(function ($item) use ($list, $key, $value) {
                if ($item instanceof \stdClass) {
                    $item->{$key} = $list[$item->{$value}];
                } else {
                    $item[$key] = $list[$item[$value]];
                }
                return $item;
            }, $types);
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public static function random()
    {
        $list = static::list();
        $randomIndex = rand(0, (count($list) - 1));
        return $list[$randomIndex];
    }

    public static function in()
    {
        return 'in:' . implode(',', static::list());
    }
}
