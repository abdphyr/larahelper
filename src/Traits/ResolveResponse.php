<?php

namespace Abd\Larahelpers\Traits;

trait ResolveResponse
{
    /**
     * HTTP response.
     * @var mixed
     */
    protected $response;

    /**
     * HTTP status code.
     * @var mixed
     */
    protected $code;

    public function setResponseData(int $status = null, string $message = null, mixed $data = null, int $code = 200)
    {
        $this->response = [
            'status' => $status ?? 1,
            'message' => $message ?? 'Success',
            'data' => $data
        ];

        $this->code = $code;
    }

    public function makeResponse(int $status = null, string $message = null, mixed $data = null, int $code = 200)
    {
        $this->response = [
            'status' => $status ?? 1,
            'message' => $message ?? 'Success',
            'data' => $data
        ];

        $this->code = $code;

        return $this->response();
    }
    
    protected function response()
    {
        if ($this->code == 204) {
            
            return response()->json(status: 204);

        } elseif ($this->response['status'] == 1) {

            return $this->response['data'];

        } elseif ($this->response['status'] == 0) {

            return response()->json($this->response, $this->code);
        }
    }
}
