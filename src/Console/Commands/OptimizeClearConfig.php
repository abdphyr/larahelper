<?php

namespace Abd\Larahelpers\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class OptimizeClearConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'occ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run optimize and clear:config commands';

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
        Artisan::call('optimize');
        Artisan::call('config:clear');
        $this->info("Optimize and config cleared");
        return 0;
    }
}
