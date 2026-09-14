<?php

namespace App\Console\Commands;

use Database\Seeders\DemoWorkshopAccountsSeeder;
use Illuminate\Console\Command;

class SeedDemoWorkshopAccounts extends Command
{
    protected $signature = 'demo:seed-workshop-accounts {--force : Run outside local/testing}';

    protected $description = 'Seed DIVESA/Brothers workshop accounts and assign maintenances idempotently';

    public function handle(): int
    {
        $seeder = new DemoWorkshopAccountsSeeder;

        if (! $seeder->shouldRun() && ! $this->option('force')) {
            $this->error('This command only runs in local/testing unless --force is passed.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            config(['database.demo_workshop_accounts' => true]);
        }

        $this->call('db:seed', [
            '--class' => DemoWorkshopAccountsSeeder::class,
        ]);

        return self::SUCCESS;
    }
}
