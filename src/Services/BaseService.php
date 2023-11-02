<?php

namespace Abd\Larahelpers\Services;

use Abd\Larahelpers\Traits\BaseServiceActions;
use Abd\Larahelpers\Traits\ResolveResponse;
use Closure;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    use ResolveResponse, BaseServiceActions;

    public function withTransaction(Closure $executer = null, Closure $catch = null, Closure $then = null)
    {
        $data = null;
        try {
            DB::beginTransaction();
            if ($executer && $executer instanceof Closure) {
                $data = $executer();
            }
            DB::commit();
            if ($then && $then instanceof Closure) {
                $then($data);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            $code = $th->getCode();
            if(!is_numeric($code)) $code = 500;
            if ($catch && $catch instanceof Closure) {
                $catch($th->getMessage(), $code);
            }
        }
    }
}
