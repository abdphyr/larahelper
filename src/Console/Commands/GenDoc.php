<?php

namespace Abd\Larahelpers\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\VarDumper\VarDumper;

class GenDoc extends Command
{
    const OBJ = "object";
    const NUM = "integer";
    const ARR = "array";
    const STR = "string";
    const BOO = "boolean";

    protected $signature = "gen:doc 
        {--c= : The name of the controller} 
        {--i=1 : index action method} 
        {--sh=1 : show action method} 
        {--s=1 : store action method} 
        {--u=1 : uupdate action method} 
        {--d=1 : destroy action method} 
        {--o=1 : other action method} 
        {--acts= : controller action methods} 
        {--clear=1 : clear cache and save to cache routes}
        {--p=Http/Controllers} 
        {--n=App\\Http\\Controllers}";

    protected $description = 'Create swagger document for given controller';

    protected $namespace = 'App\\Http\\Controllers\\';

    protected $controllersPath = "/docs/routes";

    protected $docsPath = "/docs";

    protected $mainDocFile = 'master';

    protected $authUrl = 'api/auth/login';

    private $actions = [];

    protected $prefixes = [];

    protected $authData = [];


    public function __construct()
    {
        $this->controllersPath = public_path($this->controllersPath);
        $this->docsPath = public_path($this->docsPath);
        parent::__construct();
    }

    public function handle()
    {
        try {

            $this->bootstrap();
            
            if ($this->clear()) {
                if ($controller = $this->controller()) {
                    $path = $this->actionsFilePath($controller);
                    if (file_exists($path)) unlink($path);
                    else {
                        $this->error("$controller api document files not found");
                        exit;
                    }
                    $docsPath = $this->docsPath($controller);
                    if (file_exists($docsPath)) clrmdir($docsPath);
                    else {
                        $this->error("$docsPath folder not found");
                        exit;
                    }
                    $this->info("$controller api document files deleted successfully");
                }
            } else {
                $resolver = new MakeRequest($this->laravel);
                $routes = $this->showControllerRoutes();
                
                if (!empty($routes)) {
                    foreach ($routes as $route) {
                        
                        $route = $this->prepareRoute($route, $resolver);
                        
                        $response = $resolver->resolve($route);
                        
                        $this->writeMainDocFile($route);
                        
                        $this->writeToDocFile($route, $response);
                        
                        $this->info("JSON documentation generated successfully");
                    }
                }
            }
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }

    public function index()
    {
        return !$this->option('i');
    }

    public function show()
    {
        return !$this->option('sh');
    }

    public function store()
    {
        return !$this->option('s');
    }

    public function update()
    {
        return !$this->option('u');
    }

    public function destroy()
    {
        return !$this->option('d');
    }

    public function other()
    {
        return !$this->option('o');
    }

    public function clear()
    {
        return !$this->option('clear');
    }

    public function controllerActions()
    {
        return $this->option("acts") ? explode(',', $this->option("acts")) : null;
    }

    protected function bootstrap()
    {
        try {
            $this->getDir(public_path('docs/'));
            $path = $this->docsPath . '/' . $this->mainDocFile . '.json';
            if (!file_exists($path)) {
                $this->writeJs();
                $this->writeBlade();
                $this->writeCss();
            }
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }

    public function sliceControllerName($controller)
    {
        if (str_contains($controller, $this->namespace)) {
            return explode('_', Str::snake(substr($controller, strlen($this->namespace))))[0];
        }
    }

    protected function writeCss()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css.css');
        file_put_contents($this->getDir(public_path('css')) . '/' . $this->mainDocFile . '-api-docs.css', $css);
    }

    protected function writeBlade()
    {
        $html = file_get_contents(dirname(__DIR__) . '/assets/html.blade.php');
        $html = str_replace('maindoc', $this->mainDocFile, $html);
        file_put_contents($this->getDir(resource_path('views/docs')) . '/' . $this->mainDocFile . '-api-docs.blade.php', $html);
    }

    protected function writeJs()
    {
        $js = file_get_contents(dirname(__DIR__) . '/assets/js.js');
        $js = str_replace('maindoc', $this->mainDocFile, $js);
        file_put_contents($this->getDir(public_path('js')) . '/' . $this->mainDocFile . '-api-docs.js', $js);
    }

    protected function getDir($dir)
    {
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    public function makeEndpoint($route, $data, $status, $statusText)
    {
        $body = [];
        $body['tags'] = getterValue($route, 'tags');
        $body['description'] = getterValue($route, 'description');
        if (!empty($route['parameters'])) {
            $body['parameters'] = [];
            foreach (getterArray($route, 'parameters', 'infos') as $key => $value) {
                $body['parameters'][] = [
                    'name' => $key,
                    'in' => getterValue($value, 'in'),
                    'required' => false,
                    'schema' => [
                        'type' => $this->getType(getterValue($value, 'value')),
                        'example' => getterValue($value, 'value')
                    ]
                ];
            }
        }
        if (!empty($route['headers'])) {
            if (!isset($body['parameters'])) {
                $body['parameters'] = [];
            }
            foreach (getterArray($route, 'headers') as $key => $value) {
                if ($key === "Authorization") continue;
                $body['parameters'][] = [
                    'name' => $key,
                    'in' => 'header',
                    'required' => false,
                    'schema' => [
                        'type' => $this->getType($value),
                        'example' => $value
                    ]
                ];
            }
        }
        if (isset($route['data']) && !empty($route['data'])) {
            foreach ($route['data'] as $key => $value) {
                if ($value instanceof Closure) {
                    $route['data'][$key] = $value();
                }
            }
            $body['requestBody'] = [
                'description' => getterValue($route, 'description'),
                'content' => [
                    $route['content-type'] => [
                        'schema' => $this->parser($route['data'])
                    ]
                ]
            ];
        }
        $body['responses'] = [
            "$status" => [
                'description' => "$statusText",
                'content' => [
                    $route['content-type'] => [
                        'schema' => $this->parser($data)
                    ]
                ]
            ]
        ];
        if ($route['auth']) {
            $body['security'] = [
                [
                    'bearerAuth' => []
                ]
            ];
        }
        return $body;
    }

    public function writeToDocFile($route, $response)
    {
        $path = $this->docFilePath($route)['path'];
        $method = strtolower($route['method']);
        $status = $response->status();
        $data = json_decode($response->getContent(), true);
        if ($status != 200) {
            VarDumper::dump($data);
        }
        $document = [];
        $endpoint = $this->makeEndpoint($route, $data, $status, $response->statusText());
        if (file_exists($path)) {
            $filedata = json_decode(file_get_contents($path), true);
            foreach ($filedata as $key => $value) {
                if ($key == $method) {
                    $responses = $filedata[$method]['responses'];
                    $responses["$status"] = $endpoint['responses']["$status"];
                    $endpoint['responses'] = $responses;
                    $document[$method] = $endpoint;
                } else {
                    $document[$key] = $value;
                }
            }
            if (!isset($document[$method])) {
                $document[$method] = $endpoint;
            }
        } else {
            $document[$method] = $endpoint;
        }
        $data = Str::remove('\\', json_encode($document));
        file_put_contents($path, $data, JSON_PRETTY_PRINT);
    }

    public function writeMainDocFile($route)
    {
        $action = $this->docFilePath($route)['action'];
        $endpoint = str_replace('api', '', $route['url']);
        $baseDocPath = $this->mainDocFilePath();
        $baseDocValue = json_decode(file_get_contents($baseDocPath), true);
        if (!isset($baseDocValue['paths'][$endpoint])) {
            $baseDocValue['paths'][$endpoint] = [
                '$ref' => $route['folder'] . '/' . $action . '.json'
            ];
            file_put_contents($baseDocPath, Str::remove('\\', json_encode($baseDocValue)), JSON_PRETTY_PRINT);
        }
    }

    public function writeStart($path)
    {
        file_put_contents($path, "");
        return file_put_contents($path, "<?php \n return ");
    }

    public function writeEnd($path, $data)
    {
        return file_put_contents($path, var_export($data, true) . ";", FILE_APPEND);
    }

    public function docFilePath($route, $withCreate = true)
    {
        $basePathActions = ['index', 'store'];
        $singlePathActions = ['show', 'update', 'destroy'];
        $action = '';
        if (in_array($route['action'], $basePathActions)) {
            $action = 'get-list-store';
        } else if (in_array($route['action'], $singlePathActions)) {
            $action = 'get-one-update-delete';
        } else {
            $action = $route['action'];
        }
        $dir = $this->makePath($this->docsPath, $route['folder']);
        if ($withCreate && !file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $this->makePath($dir, $action) . '.json';
        return compact('path', 'action');
    }

    public function makePath(...$folders)
    {
        return implode('/', $folders);
    }

    public function mainDocFilePath($withCreate = true)
    {
        $path = $this->docsPath . '/' . $this->mainDocFile . '.json';
        if ($withCreate && !file_exists($path)) {
            file_put_contents($path, file_get_contents(dirname(__DIR__) . '/assets/template.json'));
        }
        return $path;
    }

    public function getType($value)
    {
        if ($this->maybeObject($value)) return self::OBJ;
        if (is_numeric($value)) return self::NUM;
        if (is_bool($value)) return self::BOO;
        if (is_string($value)) return self::STR;
        if (is_array($value)) return self::ARR;
        if (is_null($value)) return null;
    }

    public function parser($data)
    {
        switch ($this->getType($data)) {
            case self::OBJ:
                $obj["type"] = self::OBJ;
                $obj['properties'] = [];
                foreach ($data as $key => $value) {
                    $obj['properties'][$key] = $this->parser($value);
                }
                return $obj;
                break;
            case self::ARR:
                $arr["type"] = self::ARR;
                $arr['items'] = [];
                if (!empty($data)) {
                    $arr['items'] = $this->parser($data[0]);
                } else {
                    $arr['items'] = $this->parser(null);
                }
                return $arr;
                break;
            case self::NUM:
                return [
                    "type" => self::NUM,
                    "example" => $data
                ];
                break;
            case self::STR:
                return [
                    "type" => self::STR,
                    "example" => $data
                ];
                break;
            case self::BOO:
                return [
                    "type" => self::BOO,
                    "example" => $data
                ];
                break;
            case null:
                return [
                    "type" => null,
                    "example" => null
                ];
                break;
        }
    }

    public function maybeObject($array)
    {
        if (!is_array($array)) return false;
        foreach ($array as $key => $value) {
            if (is_string($key)) return true;
        }
        return false;
    }

    public function prepareRoute($route, $resolver)
    {
        $route = callerOfArray($route);
        $route['url'] = $route['uri'];
        if (is_string($route)) {
            $route = $this->getRoute($route);
        }
        if (!empty(getterArray($route, 'parameters', 'params'))) {
            $route['uri'] = route($route['name'], getterArray($route, 'parameters', 'params'));
        }
        if ($route['auth']) {
            $headers = $resolver->resolveTokenHeaders(uri: $this->authUrl, data: $this->authData);
            $route['headers'] = $headers;
        }
        return $route;
    }

    public function showControllerRoutes($controller = null)
    {
        try {
            $actions = $this->getRoutes($controller);
            $allActions = $this->getRoutesFromLaravelCache($controller);
            $actionsFilePath = $this->actionsFilePath($controller);
            if (file_exists($actionsFilePath)) {
                $controllerActions = require_once $actionsFilePath;
                $diff = false;
                if (!is_bool($controllerActions)) {
                    foreach ($controllerActions as $key => $value) {
                        if (!isset($allActions[$key])) {
                            $diff = true;
                            unset($controllerActions[$key]);
                        }
                    }
                    foreach ($actions as $key => $value) {
                        if (!isset($controllerActions[$key])) {
                            $diff = true;
                            $controllerActions[$key] = $value;
                        } else {
                            $actions[$key] = $controllerActions[$key];
                        }
                    }
                }
                if ($diff) {
                    $this->writeStart($actionsFilePath);
                    $this->writeEnd($actionsFilePath, $controllerActions);
                }
                return $actions;
            } else {
                $this->writeStart($actionsFilePath);
                $this->writeEnd($actionsFilePath, $actions);
            }
            return $actions;
        } catch (\Throwable $th) {
            dd($th->getMessage(), "Controller actionlarni php fayldan o'qiganda");
        }
    }

    public function getRoutes($controller = null)
    {
        $routes = $this->getRoutesFromLaravelCache($controller);
        if ($this->controllerActions()) $this->actions = array_merge($this->actions, $this->controllerActions());
        if ($this->index()) $this->actions[] = 'index';
        if ($this->show()) $this->actions[] = 'show';
        if ($this->store()) $this->actions[] = 'store';
        if ($this->update()) $this->actions[] = 'update';
        if ($this->destroy()) $this->actions[] = 'destroy';
        if (!empty($this->actions)) {
            $routes = array_filter($routes, function ($route) {
                return in_array($route['action'], $this->actions);
            });
        }
        return $routes;
    }

    public function getRoutesFromLaravelCache($controller = null)
    {
        $routes = Route::getRoutes()->getRoutes();
        $data = [];
        foreach ($routes as $r) {
            if (($controller ?? $this->controller()) !== $r->getControllerClass()) continue;
            $method = empty($r->methods()) ? 'get' : $r->methods()[0];
            $prefixes = explode('/', $r->getPrefix());
            $baseFolder = count($prefixes) > 1 ? $prefixes[1] : $prefixes[0];
            $route = [];
            $route['uri'] = $r->uri();
            $route['name'] = $r->getName();
            $route['prefix'] = $r->getPrefix();
            $route['folder'] = $this->makePath($baseFolder, $this->model($controller ?? $this->controller()));
            $route['action'] = $r->getActionMethod();
            $route['method'] = $method;
            $params = [];
            if (str_contains($route['uri'], '{')) {
                $url = $route['uri'];
                $index = strpos($url, '{');
                while ($index) {
                    $url = substr($url, $index + 1);
                    $i = strpos($url, '}');
                    $param = substr($url, 0, $i);
                    $params[] = $param;
                    $url = substr($url, $i + 1);
                    $index = strpos($url, '{');
                }
            }
            $route['parameters'] = ['params' => [], 'infos' => []];
            if (($route['action'] == 'index') || ($route['method'] == 'POST')) {
                unset($route['parameters']);
            }
            if (!empty($params)) {
                $route['parameters']['params'] = [];
                $route['parameters']['infos'] = [];
                foreach ($params as $p) {
                    $route['parameters']['params'][$p] = 1;
                    $route['parameters']['infos'][$p] = ['in' => 'path', 'value' => 1];
                }
            }
            if (!in_array($method, ['GET', 'DELETE'])) {
                $rules = [];
                $details = new  ReflectionMethod($controller ?? $this->controller(), $route['action']);
                $parameters = $details->getParameters();
                foreach ($parameters as $p) {
                    if (!is_null($arg = $p->getType())) {
                        try {
                            $dto = $arg->getName();
                            $obj = new $dto();
                        } catch (\Throwable $th) {
                            dd($th->getMessage());
                        }
                        $rules = $obj->rules();
                        $rules = $this->summer($rules);
                    }
                }
                $route['data'] = $rules;
            }
            $route['tags'] = [$this->model($controller ?? $this->controller())];
            $route['description'] = '';
            $route['content-type'] = 'application/json';
            $route['auth'] = true;
            $data[$r->getName()] = $route;
        }
        return $data;
    }

    public function actionsFilePath($controller = null)
    {
        if (!file_exists($this->controllersPath)) {
            mkdir($this->controllersPath, 0777, true);
        }
        return $this->makePath($this->controllersPath, $this->model($controller ?? $this->controller())) . '.php';
    }

    public function docsPath($controller = null)
    {
        $routes = $this->getRoutes($controller);
        if (!empty($routes)) {
            return $this->makePath($this->docsPath, array_values($routes)[0]['folder']);
        } else {
            return $this->makePath($this->docsPath, $this->mainDocFile, $this->model($controller));
        }
    }

    protected function controller()
    {
        $controllers = fileFinder(
            name: $this->option('c'),
            path: app_path($this->option('p')),
            namespace: $this->option('n')
        );
        if (empty($controllers)) {
            $this->error($this->option('c') . " not found");
            die;
        }
        return $controllers[0];
    }

    protected function model($controller = null)
    {
        $namespace = explode('\\', $controller);
        $name = end($namespace);
        return strtolower(str_replace('Controller', '', $name));
    }

    private function summer(array $rules)
    {
        $result = [];
        foreach ($rules as $key => $value) {
            $keys = explode('.', $key);
            if (count($keys) == 1) {
                [$result, $key, $value] = $this->typer($result, $key, $value);
            }
            if (count($keys) == 2 && $keys[1] == '*') {
                if (isset($result[$keys[0]])) {
                    $data = [];
                    [$data, $key, $value] = $this->typer($data, $key, $value);
                    $result[$keys[0]][] = $data[$key];
                }
            }
            if (count($keys) == 3 && $keys[1] == '*') {
                if (!isset($result[$keys[0]][$keys[2]])) {
                    $data = [];
                    [$data, $key, $value] = $this->typer($data, $key, $value);
                    $result[$keys[0]][$keys[2]] = $data[$key];
                }
            }
        }
        return $result;
    }

    public function typer($result, $key, $value)
    {
        if (is_array($value)) {
            if (in_array('array', $value)) {
                $result[$key] = [];
            } else if (in_array('string', $value)) {
                $result[$key] = Str::random(10);
            } else if (in_array('integer', $value)) {
                $result[$key] = rand(100, 100000);
            } else if (in_array('bool', $value)) {
                $result[$key] = [true, false][rand(0, 1)];
            } else {
                $result[$key] = 1;
            }
        } else {
            if (str_contains($value, 'array')) {
                $result[$key] = [];
            } else if (str_contains($value, 'string')) {
                $result[$key] = Str::random(10);
            } else if (str_contains($value, 'integer')) {
                $result[$key] = rand(100, 100000);
            } else if (str_contains($value, 'bool')) {
                $result[$key] = [true, false][rand(0, 1)];
            } else {
                $result[$key] = 1;
            }
        }
        return [$result, $key, $value];
    }
}


class MakeRequest
{
    public function __construct(protected $app)
    {
    }

    protected function transformHeadersToServerVars(array $headers)
    {
        return collect(array_merge([], $headers))->mapWithKeys(function ($value, $name) {
            $name = strtr(strtoupper($name), '-', '_');
            return [$this->formatServerHeaderKey($name) => $value];
        })->all();
    }

    protected function formatServerHeaderKey($name)
    {
        if (!Str::startsWith($name, 'HTTP_') && $name !== 'CONTENT_TYPE' && $name !== 'REMOTE_ADDR') {
            return 'HTTP_' . $name;
        }
        return $name;
    }

    protected function extractFilesFromDataArray(&$data)
    {
        $files = [];
        foreach ($data as $key => $value) {
            if ($value instanceof SymfonyUploadedFile) {
                $files[$key] = $value;
                unset($data[$key]);
            }
            if (is_array($value)) {
                $files[$key] = $this->extractFilesFromDataArray($value);
                $data[$key] = $value;
            }
        }
        return $files;
    }

    public function request($method, $uri, $parameters = [], array $data = [], array $headers = [], $contentType)
    {
        $files = $this->extractFilesFromDataArray($data);
        $content = json_encode($data);
        $headers = array_merge([
            'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
            'CONTENT_TYPE' => $contentType,
            'Accept' => $contentType,
        ], $headers);
        return $this->call(
            $method,
            $uri,
            $parameters,
            $this->prepareCookiesForJsonRequest(),
            $files,
            $this->transformHeadersToServerVars($headers),
            $content
        );
    }

    public function resolve($route)
    {
        $check = $this->optional($route);
        $checkParam = $this->optional($check('parameters'));
        $response = $this->request(
            method: $route['method'],
            uri: $route['uri'],
            data: $check('data'),
            parameters: $checkParam('params'),
            headers: $check('headers'),
            contentType: $route['content-type']
        );
        return $response;
    }

    public function resolveTokenHeaders($uri, $data)
    {
        $response = $this->request(
            method: "POST",
            uri: $uri,
            data: $data,
            parameters: [],
            headers: [],
            contentType: 'application/json'
        );
        if ($response->isOk()) {
            $data = json_decode($response->getContent());
            return ["Authorization" => "Bearer $data->access_token"];
        } else return [];
    }

    public function resolveToken($loginRoute)
    {
        return $this->resolve($loginRoute)?->getData()?->access_token;
    }

    public function optional($route)
    {
        return function ($property) use ($route) {
            return isset($route[$property]) ? $route[$property] : [];
        };
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = $this->app->make(HttpKernel::class);
        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));
        $symfonyRequest = SymfonyRequest::create(
            uri: $this->prepareUrlForRequest($uri),
            method: $method,
            parameters: $parameters,
            cookies: $cookies,
            files: $files,
            server: array_replace([], $server),
            content: $content
        );

        $response = $kernel->handle(
            $request = Request::createFromBase($symfonyRequest)
        );
        $kernel->terminate($request, $response);
        return $response;
    }

    protected function prepareUrlForRequest($uri)
    {
        if (Str::startsWith($uri, '/')) {
            $uri = substr($uri, 1);
        }
        return trim(url($uri), '/');
    }

    protected function prepareCookiesForJsonRequest()
    {
        return [];
    }
}
