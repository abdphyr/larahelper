<?php

if (!function_exists('clsdir')) {
    /** Clear given directory directory */
    function clsdir($dirname)
    {
        $files = glob($dirname . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                clsdir($file);
                rmdir($file);
            }
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

if (!function_exists('clrmdir')) {
    /** Remove directory */
    function clrmdir($dirname)
    {
        if (is_dir($dirname)) {
            clsdir($dirname);
            rmdir($dirname);
        }
    }
}


if (!function_exists('getterArray')) {
    /** Get key's value from nested array. 
     * If have no key array, return empty array instead exception */
    function getterArray($array, ...$keys)
    {
        return getterArrayProperties($array, $keys);
    }
}


if (!function_exists('getterArrayProperties')) {
    function getterArrayProperties($array, $keys)
    {
        try {
            if (empty($keys)) {
                return $array;
            }
            $key = $keys[0];
            unset($keys[0]);
            $keys = array_merge([], $keys);
            if (isset($array[$key])) {
                if (!empty($keys)) {
                    return getterArrayProperties($array[$key], $keys);
                } else {
                    return $array[$key];
                }
            } else {
                return [];
            }
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }
}

if (!function_exists('callerOfArray')) {
    /** Call if array have callable value and set to array */
    function callerOfArray($array)
    {
        foreach ($array as $key => $value) {
            if ($value instanceof Closure) {
                $result = $value();
                if (is_array($result)) {
                    $array[$key] = callerOfArray($result);
                } else {
                    $array[$key] = $result;
                }
            } else {
                if (is_array($value)) {
                    $array[$key] = callerOfArray($value);
                }
            }
        }
        return $array;
    }
}


if (!function_exists('getterValue')) {
    function getterValue($array, $key)
    {
        return is_array($array) && isset($array[$key]) ? $array[$key] : null;
    }
}

if (!function_exists('fileFinder')) {
    function fileFinder(string $name, string $path, string|null $namespace)
    {
        $items = scandir($path);
        $results = [];
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            if ($item == $name . '.php') {
                $results[] = $namespace ? "$namespace\\$name" : $name;
            }
            if (is_dir("$path/$item")) {
                $dirResults = fileFinder(name: $name, path: "$path/$item", namespace: "$namespace\\$item");
                $results = array_merge($results, $dirResults);
            }
        }
        return $results;
    }
}


if (!function_exists("parseToRelation")) {
    function parseToRelation($relations = null)
    {
        $parsed = [];
        foreach ($relations as $key => $relation) {
            if (is_string($key)) {
                if ($relation instanceof Closure) $parsed[$key] = $relation;
                else if (is_array($relation)) $parsed[$key] = makeWithRelationQuery($relation);
            } else $parsed[] = $relation;
        }
        return $parsed;
    }
}

if (!function_exists("makeWithRelationQuery")) {
    function makeWithRelationQuery($relation)
    {
        $selects = [];
        $withs = [];
        if (empty($relation)) return fn ($q) => $q->select('*');
        $order = false;
        $closures = [];
        foreach ($relation as $key => $value) {
            if (is_numeric($key) && $value instanceof Closure) {
                $closures[] = $value;
                continue;
            }
            if ($key === 'order') {
                if (is_string($value) && !empty($value)) $order = $value;
                continue;
            }
            if (is_string($key)) {
                if ($value instanceof Closure) $withs[$key] = $value;
                else if (is_array($value)) $withs[$key] = makeWithRelationQuery($value);
            } else {
                if (str_contains($value, '|')) {
                    $selects[] = explode("|", $value)[0];
                    $order = $value;
                } else $selects[] = $value;
            }
        }
        return function ($q) use ($selects, $withs, $order, $closures) {
            if ($order) {
                $sort = explode('|', $order);
                $use = isset($sort[1]) && (strtolower($sort[1]) === 'asc' || strtolower($sort[1]) === 'desc');
                $q->orderBy($sort[0], $use ? strtolower($sort[1]) : 'asc');
            }
            if (empty($selects)) $q->select('*');
            else $q->selectRaw(implode(',', $selects));

            if (!empty($withs)) $q->with($withs);
            foreach ($closures as $closure) $closure($q);
        };
    }
}
