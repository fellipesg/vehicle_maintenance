<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Filtra, localmente, os arquivos "Estabelecimentos" do CNPJ aberto da Receita Federal e gera um CSV
 * pequeno de oficinas de uma cidade, pronto para outreach:import. Lê linha a linha (os arquivos têm GBs).
 */
class ExtractReceitaProspects extends Command
{
    /**
     * Palavras que indicam e-mail de contabilidade (parte local ou domínio).
     *
     * @var list<string>
     */
    private const ACCOUNTANT_KEYWORDS = ['contab', 'contador', 'escritorio', 'assessoria'];

    /**
     * @var list<string>
     */
    private const OUTPUT_COLUMNS = ['cnpj', 'trade_name', 'email', 'phone', 'cnae', 'street', 'number', 'neighborhood', 'cep', 'city', 'state'];

    protected $signature = 'outreach:extract-receita
        {estabelecimentos* : Arquivos Estabelecimentos da Receita}
        {--municipios= : Arquivo Municipios da Receita}
        {--city=LONDRINA : Municipio}
        {--uf=PR : UF}
        {--cnae=* : CNAE principal de 7 digitos, padrao em config outreach.default_cnaes}
        {--output= : CSV de saida, padrao em storage/app/private/outreach}';

    protected $description = 'Extrai oficinas de uma cidade dos arquivos abertos de CNPJ da Receita Federal';

    public function handle(): int
    {
        $files = (array) $this->argument('estabelecimentos');
        $city = (string) $this->option('city');
        $uf = strtoupper((string) $this->option('uf'));
        $cnaes = array_values(array_filter((array) $this->option('cnae'))) ?: config('outreach.default_cnaes');
        $threshold = (int) config('outreach.shared_email_threshold');

        foreach ($files as $file) {
            if (! is_file($file)) {
                $this->error("Arquivo não encontrado: {$file}");

                return self::FAILURE;
            }
        }

        $municipiosFile = (string) $this->option('municipios');
        if ($municipiosFile === '' || ! is_file($municipiosFile)) {
            $this->error('Informe --municipios com o arquivo Municipios da Receita.');

            return self::FAILURE;
        }

        $cityCode = $this->resolveCityCode($municipiosFile, $city);
        if ($cityCode === null) {
            $this->error("Município \"{$city}\" não encontrado em {$municipiosFile}.");

            return self::FAILURE;
        }

        $emailUsage = [];
        $candidates = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'r');
            while (($line = fgets($handle)) !== false) {
                $row = str_getcsv(mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'), ';', '"', '');

                if (count($row) < 28 || ltrim((string) $row[20], '0') !== ltrim($cityCode, '0') || strtoupper(trim((string) $row[19])) !== $uf) {
                    continue;
                }

                $email = mb_strtolower(trim((string) $row[27]));
                if ($email !== '') {
                    $emailUsage[$email] = ($emailUsage[$email] ?? 0) + 1;
                }

                if (trim((string) $row[5]) === '02' && in_array(trim((string) $row[11]), $cnaes, true)) {
                    $candidates[] = $row;
                }
            }
            fclose($handle);
        }

        $kept = [];
        $dropped = ['sem_email' => 0, 'email_invalido' => 0, 'email_compartilhado' => 0, 'palavra_contabilidade' => 0, 'duplicado' => 0];

        foreach ($candidates as $row) {
            $email = mb_strtolower(trim((string) $row[27]));
            $cnpj = str_pad(trim((string) $row[0]), 8, '0', STR_PAD_LEFT).str_pad(trim((string) $row[1]), 4, '0', STR_PAD_LEFT).str_pad(trim((string) $row[2]), 2, '0', STR_PAD_LEFT);

            if ($email === '') {
                $dropped['sem_email']++;
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $dropped['email_invalido']++;
            } elseif (($emailUsage[$email] ?? 0) >= $threshold) {
                $dropped['email_compartilhado']++;
            } elseif ($this->looksLikeAccountant($email)) {
                $dropped['palavra_contabilidade']++;
            } elseif (isset($kept[$cnpj])) {
                $dropped['duplicado']++;
            } else {
                $kept[$cnpj] = [
                    $cnpj,
                    trim((string) $row[4]),
                    $email,
                    trim((string) $row[21]).trim((string) $row[22]),
                    trim((string) $row[11]),
                    trim(trim((string) $row[13]).' '.trim((string) $row[14])),
                    trim((string) $row[15]),
                    trim((string) $row[17]),
                    trim((string) $row[18]),
                    mb_strtoupper($city),
                    $uf,
                ];
            }
        }

        $output = (string) ($this->option('output') ?: storage_path('app/private/outreach/receita-'.Str::slug($city).'-'.now()->format('Y-m-d').'.csv'));
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }

        $out = fopen($output, 'w');
        fputcsv($out, self::OUTPUT_COLUMNS, ',', '"', '');
        foreach ($kept as $record) {
            fputcsv($out, $record, ',', '"', '');
        }
        fclose($out);

        $this->info('Mantidos: '.count($kept));
        foreach ($dropped as $reason => $count) {
            $this->line("Descartados ({$reason}): {$count}");
        }
        $this->info("CSV: {$output}");

        return self::SUCCESS;
    }

    private function looksLikeAccountant(string $email): bool
    {
        foreach (self::ACCOUNTANT_KEYWORDS as $keyword) {
            if (str_contains($email, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function resolveCityCode(string $municipiosFile, string $city): ?string
    {
        $wanted = $this->normalize($city);
        $handle = fopen($municipiosFile, 'r');

        while (($line = fgets($handle)) !== false) {
            $row = str_getcsv(mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'), ';', '"', '');
            if (count($row) >= 2 && $this->normalize((string) $row[1]) === $wanted) {
                fclose($handle);

                return trim((string) $row[0]);
            }
        }

        fclose($handle);

        return null;
    }

    private function normalize(string $value): string
    {
        return strtoupper(trim(Str::ascii($value)));
    }
}
