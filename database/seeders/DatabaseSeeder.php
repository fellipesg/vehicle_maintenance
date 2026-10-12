<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            $this->call([
                // Sem o catálogo, marca e modelo são listas fechadas vazias: o
                // formulário de veículo do portal fica impossível de enviar
                // (select só com o placeholder e required). Fora dos testes,
                // que o chamam um a um, nada executava este seeder.
                VehicleCatalogSeeder::class,
                DevPortalUsersSeeder::class,
            ]);
        }
    }
}
