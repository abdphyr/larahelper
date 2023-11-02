<?php

namespace Abd\Larahelpers\Console\Commands;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Illuminate\Support\Str;
use ReflectionMethod;


class GenerateTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen:test {--c=} {--p=Http/Controllers} {--n=App\\Http\\Controllers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate test class for controller';

    protected $namespace = 'App\\Http\\Controllers\\';

    protected $prefixes = [];

    protected $authData = [
        "grant_type" => "password",
        "client_secret" => "Mt57LfRyUwwWIuKfSXnNzQAeWxQY0JFNerkrLymd",
        "client_id" => 2,
        "username" => "AN0657",
        "password" => "Adm@0657"
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $resolver = new MakeRequest($this->laravel);
        $struct = new Structurer;
        $testName = $this->makeTestName();
        $baseName = $this->getBaseName();
        $controllers = $this->getControllers();
        foreach ($controllers as $controller) {
            $routes = $this->getRoutesFromLaravelCache($controller);
            $headers = $resolver->resolveTokenHeaders(uri: 'api/auth/login', data: $this->authData);
            $data = "";
            foreach ($routes as $r) {
                $method = strtolower($r['method']);
                $action = $this->getStub($method);
                $replacer = $this->replacer($action);
                $replacer('example', $r['action']);
                $replacer('{route}', "'" . $r['name'] . "'");
                $replacer('{method}', $method);

                if($method == "get") {
                    $response = $resolver->resolve($r, $headers);
                    $content = json_decode($response->getContent(), true);
                    $structure = $struct->makeStruct($content);
                    $fragment = $struct->makeFragment($content);
                    $replacer('{structure}', $this->replaceArray(json_encode($structure)));
                    if($r['action'] == 'index') {
                        $replacer('{fragment}', '');
                        $replacer('{fragmentMethod}', '');
                    } else {
                        $replacer('{fragment}', '$fragment = ' . $this->replaceArray(json_encode($fragment) . ';'));
                        $replacer('{fragmentMethod}', "\n\t\t\t" . '->assertJsonFragment($fragment)');
                    }
                    $replacer('{parameters}', $this->replaceArray(json_encode($r['parameters'])));
                } else if ($method == 'post') {
                    $replacer('{data}', $this->replaceArray(json_encode($r['data'])));
                } else if ($method == 'put') {
                    $replacer('{data}', $this->replaceArray(json_encode($r['data'])));
                    $replacer('{parameters}', $this->replaceArray(json_encode($r['parameters'])));
                } else if ($method == 'delete') {
                    $replacer('{parameters}', $this->replaceArray(json_encode($r['parameters'])));
                }
                
                $data .= $action;
            }
            $basePath = base_path('tests/Feature');
            $file = $basePath . '/' . $testName . '.php';
            $stub = $this->getStub('base');
            $stub = str_replace('{methods}', $data, $stub);
            $stub = $this->replaceString($baseName, $stub);
            file_put_contents($file, $stub);
            $this->info("$testName created successfully");
        }
    }

    private function replaceArray($data) 
    {
        $data = str_replace('{', '[', $data);
        $data = str_replace('}', "]", $data);
        $data = str_replace(':', "=>", $data);
        return $data;
    }
    private function replacer(&$data) 
    {
        return function ($search, $replace) use (&$data) {
            $data = str_replace($search, $replace, $data);
            return $data;
        };
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
            if (in_array('date', $value)) {
                $result[$key] = date("Y-m-d H:i:s");
            } elseif (in_array('array', $value)) {
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
            if (str_contains($value, 'date')) {
                $result[$key] = date("Y-m-d H:i:s");
            } else if (str_contains($value, 'array')) {
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

    protected function replaceString($name, $stub)
    {
        $stub = str_replace('Examples', Str::plural($name), $stub);
        $stub = str_replace('examples', strtolower(Str::snake(Str::plural($name))), $stub);
        $stub = str_replace('Example', $name, $stub);
        $stub = str_replace('example', strtolower(Str::snake($name)), $stub);
        return $stub;
    }
    protected function getStub($type)
    {
        return file_get_contents('app/Console/Commands/test/' . $type . '.stub');
    }

    protected function getControllers()
    {
        $name = $this->getControllerName();
        $path = app_path($this->option('p'));
        $namespace = $this->option('n');
        $controllers = $this->finder(name: $name, path: $path, namespace: $namespace);
        return $controllers;
    }

    protected function getControllerName()
    {
        return $this->getBaseName() . 'Controller';
    }

    protected function makeTestName()
    {
        return $this->getBaseName() . 'Test';
    }

    protected function getBaseName()
    {
        $name = $this->option('c');
        $c = 'Controller';
        return (str_contains($name, $c)) ? str_replace($c, '', $name) : $name;
    }

    protected function finder(string $name, string $path, string|null $namespace)
    {
        $items = scandir($path);
        $results = [];
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            if ($item == $name . '.php') {
                $results[] = $namespace ? "$namespace\\$name" : $name;
            }
            if (is_dir("$path/$item")) {
                $dirResults = $this->finder(name: $name, path: "$path/$item", namespace: "$namespace\\$item");
                $results = array_merge($results, $dirResults);
            }
        }
        return $results;
    }

    protected function getIgnoreMethods()
    {
        try {
            $ref = new ReflectionClass($this->option('n') . '\Controller');
            $ignores = array_map(fn ($i) => $i->name, $ref->getMethods());
            $ignores[] = '__construct';
            return $ignores;
        } catch (\Throwable $th) {
            return;
        }
    }


    public function getRoutesFromLaravelCache($controller)
    {
        $routes = Route::getRoutes()->getRoutes();
        $data = [];
        foreach ($routes as $r) {
            if ($controller !== $r->getControllerClass()) continue;
            $route = [];
            $route['uri'] = $r->uri();
            $route['name'] = $r->getName();
            $route['prefix'] = $r->getPrefix();
            $route['action'] = $r->getActionMethod();
            $route['method'] = empty($r->methods()) ? 'get' : $r->methods()[0];

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
            $route['parameters'] = [];
            $route['data'] = [];
            if (!empty($params)) {
                foreach ($params as $p) {
                    $route['parameters'][$p] = 1;
                }
            }

            if (!in_array($route['method'], ['GET', 'DELETE'])) {
                $rules = [];
                $details = new  ReflectionMethod($controller, $route['action']);
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
            $route['content-type'] = 'application/json';
            $route['headers'] = [];
            $route['auth'] = true;
            $data[] = $route;
        }
        return $data;
    }
}

class Structurer
{
    const OBJ = "object";
    const NUM = "integer";
    const ARR = "array";
    const STR = "string";
    const BOO = "boolean";


    public function makeStruct($data)
    {
        if ($this->getType($data) === self::OBJ) {
            foreach ($data as $key => $value) {
                if ($this->getType($value) !== self::ARR && $this->getType($value) !== self::OBJ) {
                    unset($data[$key]);
                    $data[] = $key;
                } else if ($this->getType($value) === self::OBJ) {
                    $data[$key] = $this->makeStruct($value);
                } else if ($this->getType($value) === self::ARR) {
                    if (!empty($value)) $data[$key] = ["*" => $this->makeStruct($value[0])];
                    else $data[$key] = [];
                }
            }
        } else if ($this->getType($data) === self::ARR) {
            if (!empty($data)) $data = ['*' => $this->makeStruct($data[0])];
            else $data = [];
        }
        return $data;
    }

    public function makeFragment($data, $offset = 1)
    {
        $d = [];
        if ($this->getType($data) === self::OBJ) {
            $c = 0;
            foreach ($data as $key => $value) {
                if($c >= $offset) break;
                $d[$key] = $this->makeFragment($value, $offset);
                $c++;
            }
        } else if ($this->getType($data) === self::ARR) {
            if (!empty($data)) $d = [$this->makeFragment($data[0], $offset)];
            else $d = [];
        } else $d = $data;
        return $d;
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

    public function maybeObject($array)
    {
        if (!is_array($array)) return false;
        foreach ($array as $key => $value) return is_string($key);
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
            method: $method,
            uri: $uri,
            parameters: $parameters,
            cookies: $this->prepareCookiesForJsonRequest(),
            files: $files,
            server: $this->transformHeadersToServerVars($headers),
            content: $content
        );
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

    public function resolve($route, array $headers = [])
    {
        $response = $this->request(
            method: $route['method'],
            uri: $route['uri'],
            data: $route['data'],
            parameters: $route['parameters'],
            headers: array_merge($route['headers'], $headers),
            contentType: $route['content-type']
        );
        return $response;
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
