<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigratePlatform extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:platform {--fresh} {--seed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run platform migrations on the mercature DB';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $options = [
            '--database' => 'mercatura',
            '--path' => 'database/migrations/platform',
        ]; 

        if ($this->option('fresh')) {
            Artisan::call('migrate:fresh', $options, $this->output);
        } else {
            Artisan::call('migrate', $options, $this->output);
        }

        if ($this->option('seed')) {
            Artisan::call('db:seed', [
                '--database' => 'mercatura',
                '--force' => true,
            ], $this->output);
        }
    }
}
