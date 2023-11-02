<?php

namespace Abd\Larahelpers\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateCrud extends Command
{
    const COMMAND = "make:crud ";
    const PARAM = "{name : Name of the class or model} ";
    const UUID = "{--u=1 : Generate model with HasUuid trait and migration with uuid column} ";
    const TRANSLATE = "{--t=1 : Generate translation classes} ";
    const SOFT_DELETE = "{--d=1 : Generate model with soft delete trait} ";
    const FACTORY = "{--f=1 : Generate or no factory class} ";
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = self::COMMAND . self::PARAM . self::UUID . self::TRANSLATE . self::SOFT_DELETE . self::FACTORY;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Helper command to create CRUD files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->generateModel();
        $this->generateTranslation();
        $this->generateRequest();
        $this->generateController();
        $this->generateResource();
        $this->generateRepository();
        $this->generateService();
        $this->generateMigration();
        $this->generateFactory();
        return 0;
    }

    public function generateTranslation()
    {
        $name = $this->argument('name');
        if ($this->withTranslate()) {
            $path = app_path('Models');
            $file = $path . '/' . $name . 'Translation.php';
            if (!file_exists($file)) {
                $stub = $this->getStub('model_translation');
                $stub = $this->replaceString($name, $stub);
                file_put_contents($file, $stub);
                $this->info('Model translation created successfully.');
            }
        }
    }

    public function generateModel()
    {
        $name = $this->argument('name');
        $usings = "";
        $using = "";
        $path = app_path('Models');
        $file = $path . '/' . $name . '.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('model');
            if ($this->withUuid()) {
                $usings = "use App\Traits\HasUuid;";
                $using = "use HasUuid";
            }
            if ($this->withTranslate()) {
                $importTrait = "use App\Traits\HasTranslation;";
                $property = 'public $translationClass = ' . $name . 'Translation::class;';
                if ($usings == '') {
                    $usings = $importTrait;
                    $using = "use HasTranslation";
                } else {
                    $usings .= PHP_EOL . $importTrait;
                    $using .= ", HasTranslation";
                }
                $stub = str_replace("{translate}", "\t" . $property . "\n", $stub);
            } else {
                $stub = str_replace("{translate}", '', $stub);
            }
            if ($this->withSoftDelete()) {
                $importTrait = "use Illuminate\Database\Eloquent\SoftDeletes;";
                if ($usings == '') {
                    $usings = $importTrait;
                    $using = "use SoftDeletes";
                } else {
                    $usings .= PHP_EOL . $importTrait;
                    $using .= ", SoftDeletes";
                }
            }
            if ($this->withFactory()) {
                $importTrait = "use App\Traits\NewFactoryTrait;";
                $importClass = "use Database\Factories\\" . $name . "Factory;";
                $property = 'protected static string $model_factory = ' . $name . 'Factory::class;';
                if ($usings == '') {
                    $usings = $importTrait . "\n" . $importClass;
                    $using = "use NewFactoryTrait";
                } else {
                    $usings .= PHP_EOL . $importTrait . "\n" . $importClass;
                    $using .= ", NewFactoryTrait";
                }
                $stub = str_replace("{factory}", "\t" . $property . "\n", $stub);
            } else {
                $stub = str_replace("{factory}", '', $stub);
            }

            if ($usings != '') {
                $stub = str_replace('{usings}', "\n" . $usings . "\n", $stub);
            } else {
                $stub = str_replace('{usings}', '', $stub);
            }
            if ($using != '') {
                $stub = str_replace('{using}', "\n\t" . $using . ";" . "\n\n", $stub);
            } else {
                $stub = str_replace('{using}', '', $stub);
            }
            $stub = $this->replaceString($name, $stub);
            file_put_contents($file, $stub);
            $this->info('Model created successfully.');
        }
    }

    public function generateFactory()
    {
        $name = $this->argument('name');
        if ($this->withFactory()) {
            $path = database_path('factories');
            $file = $path . '/' . $name . 'Factory.php';
            if (!file_exists($file)) {
                $stub = $this->getStub('factory');
                $stub = $this->replaceString($name, $stub);
                file_put_contents($file, $stub);
                $this->info('Factory created successfully.');
            }
        }
        if ($this->withTranslate()) {
            $path = database_path('factories');
            $file = $path . '/' . $name . 'TranslationFactory.php';
            if (!file_exists($file)) {
                $stub = $this->getStub('factory_translation');
                $stub = $this->replaceString($name, $stub);
                file_put_contents($file, $stub);
                $this->info('Factory translation created successfully.');
            }
        }
    }

    public function generateMigration()
    {
        $name = $this->argument('name');
        $path = database_path('migrations');
        $file = $path . '/' . date('Y_m_d_His') . '_create_' . strtolower(Str::snake(Str::plural($name))) . '_table.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('migration');
            if ($this->withUuid()) {
                $builder = "use Illuminate\Support\Facades\DB;";
                $uuidColumn = '$table' . "->uuid('uuid')->default(DB::raw('gen_random_uuid()'));";
                $uuidIndex = '$table' . "->index(['uuid']);";
                $stub = str_replace('{db}', "\n" . $builder, $stub);
                $stub = str_replace('{uuid}', "\n\t\t\t" . $uuidColumn, $stub);
                $stub = str_replace('{uuidIndex}', "\n\t\t\t" . $uuidIndex, $stub);
            } else {
                $stub = str_replace('{db}', '', $stub);
                $stub = str_replace('{uuid}', '', $stub);
                $stub = str_replace('{uuidIndex}', '', $stub);
            }
            if ($this->withSoftDelete()) {
                $softDeleteColumn = '$table' . "->softDeletes();";
                $stub = str_replace('{softDeletes}', "\n\t\t\t" . $softDeleteColumn, $stub);
            } else {
                $stub = str_replace('{softDeletes}', '', $stub);
            }
            $stub = $this->replaceString($name, $stub);
            file_put_contents($file, $stub);
            $this->info('Migration created successfully.');
        }
        if ($this->withTranslate()) {
            $path = database_path('migrations');
            $file = $path . '/' . date('Y_m_d_His') . '_create_' . strtolower(Str::snake($name)) . '_translations_table.php';
            if (!file_exists($file)) {
                $stub = $this->getStub('migration_translation');
                $stub = $this->replaceString($name, $stub);
                file_put_contents($file, $stub);
                $this->info('Migration translation created successfully.');
            }
        }
    }

    public function generateResource()
    {
        $name = $this->argument('name');
        $path = app_path('Http/Resources');
        if (!file_exists($path)) {
            mkdir($path);
        }
        $file = $path . '/' . $name . 'Resource.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('resource');
            if ($this->withUuid()) {
                $uuid = "'uuid' => " . '$this->uuid,';
                $stub = str_replace('{uuid}', "\n\t\t\t" . $uuid, $stub);
            } else {
                $stub = str_replace('{uuid}', '', $stub);
            }
            if ($this->withTranslate()) {
                $translations = "'translations' => " . '$this->translations,';
                $stub = str_replace('{translation}', "\n\t\t\t" . $translations, $stub);
            } else {
                $stub = str_replace('{translation}', '', $stub);
            }
            $stub = $this->replaceString($name, $stub);
            file_put_contents($file, $stub);
            $this->info('Resource created successfully.');
        }
    }

    public function generateRepository()
    {
        $name = $this->argument('name');
        $path = app_path('Repositories');
        $file = $path . '/' . $name . 'Repository.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('repository');
            $stub = $this->replaceString($name, $stub);
            file_put_contents($file, $stub);
            $this->info('Repository created successfully.');
        }
    }

    public function generateRequest()
    {
        $name = $this->argument('name');
        $path = app_path('Http/Requests');
        if (!file_exists($path)) {
            mkdir($path);
        }
        $file = $path . '/' . $name . 'UpsertRequest.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('request');
            if ($this->withTranslate()) {
                $tr = "'translations' => [" . '$required' . ", 'array'],
            'translations.*' => [" . '$required' . ", 'array'],
            'translations.*.id' => ['nullable'],
            'translations.*.name' => [" . '$required' . ", 'string'],";
                $stub = str_replace('{translation}', $tr, $stub);
            } else {
                $stub = str_replace('{translation}', '', $stub);
            }
            $stub = $this->replaceString($name, $stub);
            file_put_contents($file, $stub);
            $this->info('Request created successfully.');
        }
    }

    public function generateService()
    {
        $name = $this->argument('name');
        $path = app_path('Services');
        $file = $path . '/' . $name . 'Service.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('service');
            $stub = $this->replaceString($name, $stub);
            if ($this->withSoftDelete()) {
                $stub = Str::replace('delete', 'softDelete', $stub);
            }
            file_put_contents($file, $stub);
            $this->info('Service created successfully.');
        }
    }

    public function generateController()
    {
        $name = $this->argument('name');
        $path = app_path('Http/Controllers');
        $file = $path . '/' . $name . 'Controller.php';
        if (!file_exists($file)) {
            $stub = $this->getStub('controller');
            $stub = $this->replaceString($name, $stub);
            file_put_contents($file, $stub);
            $this->info('Controller created successfully.');
        }
    }

    protected function getStub($type)
    {
        return file_get_contents('app/Console/Commands/crud/' . $type . '.stub');
    }

    public function withUuid()
    {
        return !$this->option('u');
    }

    public function withTranslate()
    {
        return !$this->option('t');
    }

    public function withSoftDelete()
    {
        return !$this->option('d');
    }

    public function withFactory()
    {
        return true || !$this->option('f');
    }

    protected function replaceString($name, $stub)
    {
        $stub = str_replace('Examples', Str::plural($name), $stub);
        $stub = str_replace('examples', strtolower(Str::snake(Str::plural($name))), $stub);
        $stub = str_replace('Example', $name, $stub);
        $stub = str_replace('example', strtolower(Str::snake($name)), $stub);
        return $stub;
    }
}
