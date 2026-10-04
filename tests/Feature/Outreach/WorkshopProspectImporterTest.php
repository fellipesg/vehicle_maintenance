<?php

namespace Tests\Feature\Outreach;

use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
use App\Services\Outreach\WorkshopProspectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopProspectImporterTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'cnpj,trade_name,email,phone,cnae,street,number,neighborhood,cep,city,state';

    /**
     * @param  list<string>  $rows
     */
    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'prospects');
        file_put_contents($path, implode("\n", [self::HEADER, ...$rows])."\n");

        return $path;
    }

    public function test_it_creates_prospects_with_lowercased_email_and_a_token(): void
    {
        $path = $this->csv(['12345678000195,Oficina do Zé,ZE@Oficina.com.br,4333334444,4520001,Rua A,10,Centro,86010000,LONDRINA,PR']);

        $counts = app(WorkshopProspectImporter::class)->importFile($path);

        $this->assertSame(1, $counts['created']);
        $prospect = WorkshopProspect::sole();
        $this->assertSame('ze@oficina.com.br', $prospect->email);
        $this->assertSame(40, strlen($prospect->token));
        $this->assertSame('pending', $prospect->status->value);
        $this->assertSame('receita_cnpj', $prospect->source);
    }

    public function test_it_skips_duplicates_suppressed_customers_and_invalid_rows(): void
    {
        WorkshopProspect::factory()->create(['cnpj' => '11111111000111', 'email' => 'existente@x.com.br']);
        EmailSuppression::suppress('Fora@x.com.br', 'unsubscribed');
        Workshop::factory()->create(['email' => 'cliente@x.com.br']);
        User::factory()->create(['email' => 'usuario@x.com.br']);

        $path = $this->csv([
            '11111111000111,Dup Cnpj,novo@x.com.br,,4520001,,,,,LONDRINA,PR',
            '22222222000122,Suprimida,fora@x.com.br,,4520001,,,,,LONDRINA,PR',
            '33333333000133,Cliente,CLIENTE@x.com.br,,4520001,,,,,LONDRINA,PR',
            '44444444000144,Usuario,usuario@x.com.br,,4520001,,,,,LONDRINA,PR',
            '55555555000155,Email repetido,existente@x.com.br,,4520001,,,,,LONDRINA,PR',
            '66666666000166,Sem email,invalido,,4520001,,,,,LONDRINA,PR',
            '77777777000177,Boa,boa@x.com.br,,4520001,,,,,LONDRINA,PR',
            '88888888000188,Boa 2,boa@x.com.br,,4520001,,,,,LONDRINA,PR',
        ]);

        $counts = app(WorkshopProspectImporter::class)->importFile($path);

        $this->assertSame(['created' => 1, 'duplicates' => 3, 'suppressed' => 1, 'customers' => 2, 'invalid' => 1], $counts);
        $this->assertSame(2, WorkshopProspect::count());
    }

    public function test_import_command_reports_counts_and_rejects_a_csv_without_required_columns(): void
    {
        $path = $this->csv(['12345678000195,Zé,ze@x.com.br,,4520001,,,,,LONDRINA,PR']);

        $this->artisan('outreach:import', ['csv' => $path])
            ->expectsOutputToContain('Criadas: 1')
            ->assertSuccessful();

        $bad = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($bad, "foo,bar\n1,2\n");

        $this->artisan('outreach:import', ['csv' => $bad])
            ->expectsOutputToContain('Colunas ausentes')
            ->assertFailed();
    }
}
