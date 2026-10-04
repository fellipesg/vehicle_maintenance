<?php

namespace Tests\Feature\Outreach;

use Tests\TestCase;

class ExtractReceitaCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/outreach-'.uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    /**
     * @param  array<int, string>  $overrides
     */
    private function establishment(array $overrides): string
    {
        $row = array_fill(0, 30, '');
        $defaults = [
            0 => '12345678', 1 => '0001', 2 => '95', 4 => 'OFICINA DO ZÉ', 5 => '02', 11 => '4520001',
            13 => 'RUA', 14 => 'DAS ACÁCIAS', 15 => '10', 17 => 'CENTRO', 18 => '86010000', 19 => 'PR',
            20 => '4115', 21 => '43', 22 => '33334444', 27 => 'ze@oficina.com.br',
        ];

        foreach ($overrides + $defaults as $index => $value) {
            $row[$index] = $value;
        }

        return implode(';', array_map(fn (string $value): string => '"'.$value.'"', $row));
    }

    /**
     * @param  list<string>  $lines
     */
    private function write(string $name, array $lines): string
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, mb_convert_encoding(implode("\n", $lines)."\n", 'ISO-8859-1', 'UTF-8'));

        return $path;
    }

    private function municipios(): string
    {
        return $this->write('municipios.csv', ['"4115";"LONDRINA"', '"7107";"SÃO PAULO"', '"9999";"OUTRA"']);
    }

    /**
     * @return list<array<string, string>>
     */
    private function readOutput(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, null, ',', '"', '');
        $rows = [];
        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $values);
        }
        fclose($handle);

        return $rows;
    }

    public function test_it_keeps_only_active_matching_establishments_and_writes_the_expected_columns(): void
    {
        $file = $this->write('estab.csv', [
            $this->establishment([]),
            $this->establishment([0 => '11111111', 5 => '08', 27 => 'baixada@x.com.br']),
            $this->establishment([0 => '22222222', 19 => 'SP', 27 => 'sp@x.com.br']),
            $this->establishment([0 => '33333333', 20 => '7107', 27 => 'saopaulo@x.com.br']),
            $this->establishment([0 => '44444444', 11 => '4511101', 27 => 'concessionaria@x.com.br']),
            $this->establishment([0 => '55555555', 27 => '']),
            $this->establishment([0 => '66666666', 27 => 'invalido']),
            $this->establishment([0 => '77777777', 4 => 'FUNILARIA SÃO JOÃO', 11 => '4520004', 27 => 'FUNIL@Oficina.com.br']),
        ]);
        $output = $this->directory.'/out.csv';

        $this->artisan('outreach:extract-receita', [
            'estabelecimentos' => [$file],
            '--municipios' => $this->municipios(),
            '--city' => 'londrina',
            '--uf' => 'PR',
            '--output' => $output,
        ])->assertSuccessful();

        $rows = $this->readOutput($output);

        $this->assertSame(['cnpj', 'trade_name', 'email', 'phone', 'cnae', 'street', 'number', 'neighborhood', 'cep', 'city', 'state'], array_keys($rows[0]));
        $this->assertCount(2, $rows);
        $this->assertSame([
            'cnpj' => '12345678000195',
            'trade_name' => 'OFICINA DO ZÉ',
            'email' => 'ze@oficina.com.br',
            'phone' => '4333334444',
            'cnae' => '4520001',
            'street' => 'RUA DAS ACÁCIAS',
            'number' => '10',
            'neighborhood' => 'CENTRO',
            'cep' => '86010000',
            'city' => 'LONDRINA',
            'state' => 'PR',
        ], $rows[0]);
        $this->assertSame('funil@oficina.com.br', $rows[1]['email']);
        $this->assertSame('FUNILARIA SÃO JOÃO', $rows[1]['trade_name']);
    }

    public function test_it_drops_shared_emails_counting_every_cnae_in_the_municipality(): void
    {
        $lines = [
            $this->establishment([0 => '10000001', 27 => 'contato@grupo.com.br']),
            $this->establishment([0 => '10000002', 11 => '6920601', 27 => 'contato@grupo.com.br']),
            $this->establishment([0 => '10000003', 11 => '4711302', 27 => 'contato@grupo.com.br']),
            $this->establishment([0 => '10000004', 27 => 'dois@grupo.com.br']),
            $this->establishment([0 => '10000005', 11 => '4711302', 27 => 'dois@grupo.com.br']),
            // Mesmo e-mail em outro município não conta.
            $this->establishment([0 => '10000006', 20 => '7107', 27 => 'dois@grupo.com.br']),
        ];
        $file = $this->write('estab.csv', $lines);
        $output = $this->directory.'/out.csv';

        $this->artisan('outreach:extract-receita', [
            'estabelecimentos' => [$file],
            '--municipios' => $this->municipios(),
            '--output' => $output,
        ])
            ->expectsOutputToContain('Descartados (email_compartilhado): 1')
            ->assertSuccessful();

        $emails = array_column($this->readOutput($output), 'email');
        $this->assertSame(['dois@grupo.com.br'], $emails);
    }

    public function test_it_drops_accountant_keywords_in_local_part_or_domain(): void
    {
        $file = $this->write('estab.csv', [
            $this->establishment([0 => '20000001', 27 => 'contabil@oficina.com.br']),
            $this->establishment([0 => '20000002', 27 => 'oficina@silvacontadores.com.br']),
            $this->establishment([0 => '20000003', 27 => 'oficina@escritoriosilva.com.br']),
            $this->establishment([0 => '20000004', 27 => 'assessoria@oficina.com.br']),
            $this->establishment([0 => '20000005', 27 => 'ok@oficina.com.br']),
        ]);
        $output = $this->directory.'/out.csv';

        $this->artisan('outreach:extract-receita', [
            'estabelecimentos' => [$file],
            '--municipios' => $this->municipios(),
            '--output' => $output,
        ])
            ->expectsOutputToContain('Descartados (palavra_contabilidade): 4')
            ->assertSuccessful();

        $this->assertSame(['ok@oficina.com.br'], array_column($this->readOutput($output), 'email'));
    }

    public function test_cnae_option_overrides_the_default_and_several_files_are_read(): void
    {
        $first = $this->write('a.csv', [$this->establishment([0 => '30000001', 27 => 'a@oficina.com.br'])]);
        $second = $this->write('b.csv', [$this->establishment([0 => '30000002', 11 => '4520002', 27 => 'b@oficina.com.br'])]);
        $output = $this->directory.'/out.csv';

        $this->artisan('outreach:extract-receita', [
            'estabelecimentos' => [$first, $second],
            '--municipios' => $this->municipios(),
            '--cnae' => ['4520002'],
            '--output' => $output,
        ])->assertSuccessful();

        $this->assertSame(['b@oficina.com.br'], array_column($this->readOutput($output), 'email'));
    }

    public function test_it_fails_clearly_when_the_city_is_not_found(): void
    {
        $file = $this->write('estab.csv', [$this->establishment([])]);

        $this->artisan('outreach:extract-receita', [
            'estabelecimentos' => [$file],
            '--municipios' => $this->municipios(),
            '--city' => 'Atlântida',
            '--output' => $this->directory.'/out.csv',
        ])
            ->expectsOutputToContain('Município "Atlântida" não encontrado')
            ->assertFailed();
    }

    public function test_city_lookup_ignores_case_and_accents(): void
    {
        $file = $this->write('estab.csv', [$this->establishment([0 => '40000001', 20 => '7107', 27 => 'sp@oficina.com.br', 19 => 'SP'])]);
        $output = $this->directory.'/out.csv';

        $this->artisan('outreach:extract-receita', [
            'estabelecimentos' => [$file],
            '--municipios' => $this->municipios(),
            '--city' => 'sao paulo',
            '--uf' => 'SP',
            '--output' => $output,
        ])->assertSuccessful();

        $this->assertSame(['sp@oficina.com.br'], array_column($this->readOutput($output), 'email'));
    }
}
