<?php

namespace App\Services\Outreach;

use App\Enums\WorkshopProspectStatus;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Importa o CSV gerado por outreach:extract-receita. Quem não pode receber convite (CNPJ já importado,
 * e-mail repetido, na lista de supressão ou já cliente) NÃO é inserido: só entra o que pode ser enviado.
 */
class WorkshopProspectImporter
{
    /**
     * @var list<string>
     */
    private const REQUIRED_COLUMNS = ['cnpj', 'email', 'cnae', 'city', 'state'];

    /**
     * @return array{created: int, duplicates: int, suppressed: int, customers: int, invalid: int}
     */
    public function importFile(string $path): array
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new InvalidArgumentException("Não foi possível abrir {$path}.");
        }

        return $this->importCsv($contents);
    }

    /**
     * Importa o conteúdo do CSV já lido (o upload do admin vai pela fila com o conteúdo, sem depender
     * de arquivo no disco do servidor web).
     *
     * @return array{created: int, duplicates: int, suppressed: int, customers: int, invalid: int}
     */
    public function importCsv(string $contents): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle, null, ',', '"', '');
        if (! is_array($header)) {
            fclose($handle);
            throw new InvalidArgumentException('CSV vazio.');
        }

        $header = array_map(fn ($column): string => trim((string) $column, " \t\n\r\0\x0B\xEF\xBB\xBF"), $header);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing !== []) {
            fclose($handle);
            throw new InvalidArgumentException('Colunas ausentes no CSV: '.implode(', ', $missing).'.');
        }

        $rows = [];
        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($values === [null] || count($values) !== count($header)) {
                continue;
            }

            $rows[] = array_combine($header, $values);
        }

        fclose($handle);

        return $this->importRows($rows);
    }

    /**
     * Confere todas as linhas com poucas consultas em lote e insere em blocos: linha a linha eram
     * seis consultas por oficina e o upload de ~1.200 linhas estourava o tempo do Cloudflare (504).
     *
     * @param  list<array<string, string|null>>  $rows
     * @return array{created: int, duplicates: int, suppressed: int, customers: int, invalid: int}
     */
    public function importRows(array $rows): array
    {
        $counts = ['created' => 0, 'duplicates' => 0, 'suppressed' => 0, 'customers' => 0, 'invalid' => 0];

        $normalized = array_map(fn (array $row): array => [
            'row' => $row,
            'cnpj' => preg_replace('/\D/', '', (string) ($row['cnpj'] ?? '')) ?? '',
            'email' => mb_strtolower(trim((string) ($row['email'] ?? ''))),
        ], $rows);

        $cnpjs = array_values(array_unique(array_column($normalized, 'cnpj')));
        $emails = array_values(array_unique(array_column($normalized, 'email')));

        $existingCnpjs = $this->lookup(fn (array $chunk) => WorkshopProspect::query()->whereIn('cnpj', $chunk)->pluck('cnpj'), $cnpjs);
        $suppressedEmails = $this->lookup(fn (array $chunk) => EmailSuppression::query()->whereIn('email', $chunk)->pluck('email'), $emails);
        $customerEmails = $this->lookup(fn (array $chunk) => Workshop::query()->selectRaw('lower(email) as normalized_email')->whereIn(DB::raw('lower(email)'), $chunk)->pluck('normalized_email'), $emails)
            + $this->lookup(fn (array $chunk) => User::query()->selectRaw('lower(email) as normalized_email')->whereIn(DB::raw('lower(email)'), $chunk)->pluck('normalized_email'), $emails);
        $prospectEmails = $this->lookup(fn (array $chunk) => WorkshopProspect::query()->whereIn('email', $chunk)->pluck('email'), $emails);

        $now = now();
        $inserts = [];

        foreach ($normalized as ['row' => $row, 'cnpj' => $cnpj, 'email' => $email]) {
            if (strlen($cnpj) !== 14 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $counts['invalid']++;

                continue;
            }

            if (isset($existingCnpjs[$cnpj])) {
                $counts['duplicates']++;

                continue;
            }

            if (isset($suppressedEmails[$email])) {
                $counts['suppressed']++;

                continue;
            }

            if (isset($customerEmails[$email])) {
                $counts['customers']++;

                continue;
            }

            if (isset($prospectEmails[$email])) {
                $counts['duplicates']++;

                continue;
            }

            $existingCnpjs[$cnpj] = true;
            $prospectEmails[$email] = true;
            $counts['created']++;

            $inserts[] = [
                'cnpj' => $cnpj,
                'trade_name' => $this->nullable($row['trade_name'] ?? null),
                'legal_name' => $this->nullable($row['legal_name'] ?? null),
                'email' => $email,
                'phone' => $this->nullable($row['phone'] ?? null),
                'cnae' => (string) $row['cnae'],
                'street' => $this->nullable($row['street'] ?? null),
                'number' => $this->nullable($row['number'] ?? null),
                'neighborhood' => $this->nullable($row['neighborhood'] ?? null),
                'cep' => $this->nullable($row['cep'] ?? null),
                'city' => (string) $row['city'],
                'state' => strtoupper((string) $row['state']),
                'source' => 'receita_cnpj',
                'token' => Str::random(40),
                'status' => WorkshopProspectStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($inserts): void {
            foreach (array_chunk($inserts, 200) as $chunk) {
                WorkshopProspect::query()->insert($chunk);
            }
        });

        return $counts;
    }

    /**
     * Consulta em blocos de 500 e devolve os valores encontrados como chaves de um mapa.
     *
     * @param  callable(list<string>): iterable<string>  $query
     * @param  list<string>  $values
     * @return array<string, true>
     */
    private function lookup(callable $query, array $values): array
    {
        $found = [];

        foreach (array_chunk($values, 500) as $chunk) {
            foreach ($query($chunk) as $value) {
                $found[(string) $value] = true;
            }
        }

        return $found;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
