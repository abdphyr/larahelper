<?php

namespace Abd\Larahelpers\Traits;

trait FilterableResource
{
    public function filterFields($fields)
    {
        if(request('only') or request('except')){
            $only = explode(",", str_replace(' ', '', request('only') ?? ''));
            $except = explode(",", str_replace(' ', '', request('except') ?? ''));

            foreach ($fields as $key => $field) {
                if (request('only')) {
                    if (!in_array($field, $only)) {
                        unset($fields[$key]);
                    }
                } else {
                    if (request('except')) {
                        if (in_array($field, $except)) {
                            unset($fields[$key]);
                        }
                    }
                }
            }
        }
        return $fields;
    }
}
