<?php

namespace Abd\Larahelpers\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $serviceResult;

    protected function response()
    {
        if ($this->serviceResult['code'] == 204) return response()->json(status: 204);

        if ($this->serviceResult['response']['status'] == 1) {
            return $this->serviceResult['response']['data'];
        } else {
            return response()->json($this->serviceResult['response'], $this->serviceResult['code']);
        }
    }
}
