<?php

namespace Abd\Larahelpers\Traits;

use App\Events\ActionProcessed;
use Illuminate\Support\Facades\Request;

trait HasLog
{
    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            if (request()->user()) {
                event(new ActionProcessed(self::getData($model, 'C')));
            }
        });

        static::updated(function ($model) {
            if (request()->user()) {
                event(new ActionProcessed(self::getData($model, 'U')));
            }
        });

        static::deleting(function ($model) {
            if (request()->user()) {
                event(new ActionProcessed(self::getData($model, 'D')));
            }
        });
    }

    protected static function getData($model, $action)
    {
        return [
            'user_id' => request()->user()->id ?? null,
            'route' => request()->route()->getName() ?? null,
            'resource' => $model->getTable(),
            'resource_id' => $model->id ?? null,
            'action' => $action,
            'data' => json_encode([
                'old' => $model->getOriginal(),
                'new' => $model->getAttributes(),
            ]),
            'ip' => Request::ip(),
            'browser' => json_encode(Request::userAgent())
        ];
    }
}
