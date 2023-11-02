<?php

namespace Abd\Larahelpers\Console\Commands;

use Illuminate\Console\Command;

class StartUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'startup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('key:generate');
        sleep(1);
        $this->call('migrate:fresh');
        sleep(1);
        $this->call('passport:install', ['--force' => 1]);
        sleep(1);
        $this->call('db:seed');
        sleep(1);
        $this->call('optimize');
        sleep(1);
        $this->call('config:clear');
        sleep(1);
        $this->call('update-permissions');
    }
}
