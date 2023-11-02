<?php

namespace Abd\Larahelpers\Traits;

use Illuminate\Support\Facades\Route;

trait ChangeResource
{
    public $custom;

    public function __construct($resource, $index = 0, $custom = false)
    {
        parent::__construct($resource);
        $this->custom = $custom;
    }

    public abstract function base();

    public function show()
    {
        return [];
    }

    public function all()
    {
        return [];
    }

    public function toArray($request)
    {
        $arr = [];
        if ($this->custom) $arr = $this->base(); 
        else {
            if ($this->isShow()) {
                $arr = array_merge($this->base(), $this->show() ?? []);
            } else {
                $arr = array_merge($this->base(), $this->all() ?? []);
            }
        }
        return $arr;
    }

    public function isShow()
    {
        return str_contains(Route::currentRouteName(), 'show') || str_contains(Route::currentRouteName(), 'view');
    }

    public static function collectionCustom($resources, $adds = [])
    {
        $collection = collect();
        $i = 0;
        foreach ($resources as $resource) {
            $element = new static($resource, $i, true);
            foreach ($adds as $key => $resourceClass) {
                if (isset($resource[$key])) {
                    if (method_exists($resourceClass, 'makeCustom')) {
                        $element->adds[$key] = $resourceClass::makeCustom($resource[$key]);
                    } else {
                        $element->adds[$key] = $resourceClass::make($resource[$key]);
                    }
                }
            }
            $collection->push($element);
            $i++;
        }
        return $collection;
    }

    public static function makeCustom($resource)
    {
        return new static($resource, 0, true);
    }
}
