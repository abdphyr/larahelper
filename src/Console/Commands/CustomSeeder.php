<?php

namespace Abd\Larahelpers\Console\Commands;;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cs {--s=} {--f=} {--fs=} {--sf=} {--d=} {--m=} {--ns=Database\\Seeders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Custom runner command seeder and migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            DB::beginTransaction();
            if ($this->option('f')) $this->freshing();
            if ($this->option('s')) $this->seeding();
            if ($this->option('d')) $this->dropping();
            if ($this->option('m')) $this->migrating();
            if (!$this->option('f') && !$this->option('s') && !$this->option('d') && !$this->option('m')) $this->freshSeed();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->error($th->getMessage());
        }
    }

    public function freshSeed()
    {
        if ($fss = $this->option('fs') ?? $this->option('sf')) {
            $this->freshing($fss);
            $this->seeding($fss);
        } else {
            $this->call('migrate:fresh');
            $this->call('db:seed');
        }
    }

    public function dropping($option = null)
    {
        if ($input = $option ?? $this->option('d')) {
            foreach ($this->getMigrations($input) as $m) $this->dropTable($m);
        }
    }

    public function migrating($option = null)
    {
        if ($input = $option ?? $this->option('m')) {
            foreach ($this->getMigrations($input) as $m) $this->migrateTable($m);
        }
    }

    public function seeding($option = null)
    {
        if ($seeds = $option ?? $this->option('s')) {
            foreach (explode(',', $seeds) as $s) $this->seed($s);
        }
    }

    public function freshing($option = null)
    {
        if ($input = $option ?? $this->option('f')) {
            $this->dropping($input);
            $this->migrating($option);
        }
    }

    protected function seederExists($seeder)
    {
        return file_exists(database_path("seeders/$seeder.php"));
    }

    protected function modelExists($model)
    {
        return file_exists(public_path("Models/$model.php"));
    }

    protected function seed($seeder)
    {
        if (!Str::contains($seeder, 'Seeder')) $seeder .= 'Seeder';
        if ($this->seederExists($seeder)) {
            $class = $this->option('ns') . "\\$seeder";
            $this->newLine(1);
            $this->info("<fg=white;bg=blue>  Seeding  </>" . "  ........." . " <fg=yellow>  $class  </>");
            (new $class())?->run();
            $this->newLine(1);
            $this->info("<fg=white;bg=blue>  Seeded  </>" . "  ........." . " <fg=yellow>  $class  </>");
            $this->newLine(1);
        } else $this->error("$seeder seeder not found");
    }

    protected function dropTable($migration)
    {
        if ($migration->action == 'create') {
            Schema::dropIfExists($migration->table);
            $this->newLine(1);
            $this->info("<fg=white;bg=blue>  Dropped  </>" . "  ........." . "  $migration->table table");
        } elseif ($migration->action == 'table') {
            $this->newLine(1);
            $this->info("<fg=white;bg=blue>  Altered  </>" . "  ........." . "  $migration->table table  ");
            $this->newLine(1);
        }
    }

    protected function migrateTable($migration)
    {
        $obj = $this->getMigrationObject($migration);
        $obj?->up();
        $this->newLine(1);
        $this->info("<fg=white;bg=blue>  Migrated  </>" . "  ........." . "  $migration->migration  ");
    }

    protected function getMigrations($input, $reverse = false)
    {
        $all = collect();
        foreach (explode(',', $input) as $model) {
            if (preg_match('~^\p{Lu}~u', $model)) $model = Str::plural(Str::snake($model));
            $migration = DB::table('migrations')->where('migration', 'ilike', "%$model%")->first();
            if ($migration) {
                $content = file_get_contents(database_path("migrations/$migration->migration.php"));
                $schema = 'Schema::';
                $s = strpos($content, $schema);
                $c = strpos($content, '(', $s);
                $startPointTableName = strpos($content, "'", $c + 1);
                $endPointTableName = strpos($content, "'", $startPointTableName + 1);
                $migration->action = substr($content, $s + strlen($schema), $c - $s - strlen($schema));
                $migration->table = substr($content, $startPointTableName + 1, $endPointTableName - $startPointTableName - 1);
                $all->push($migration);
            }
        }
        if ($reverse) return $all->reverse();
        return $all;
    }

    protected function getMigrationObject($migration)
    {
        $obj = require_once database_path("migrations/$migration->migration.php");
        if (!is_object($obj) && $obj == 1) {
            $class = collect(explode('_', $migration->migration))
                ->filter(fn ($i) => !is_numeric($i))
                ->map(fn ($i) => ucfirst($i))
                ->join('');
            $obj = new $class();
        }
        return $obj;
    }
}
