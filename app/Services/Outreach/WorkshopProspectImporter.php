<?php

namespace App\Services\Outreach;

use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
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
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Não foi possível abrir {$path}.");
        }

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

        $counts = ['created' => 0, 'duplicates' => 0, 'suppressed' => 0, 'customers' => 0, 'invalid' => 0];

        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($values === [null] || count($values) !== count($header)) {
                continue;
            }

            $counts[$this->importRow(array_combine($header, $values))]++;
        }

        fclose($handle);

        return $counts;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return 'created'|'duplicates'|'suppressed'|'customers'|'invalid'
     */
    public function importRow(array $row): string
    {
        $cnpj = preg_replace('/\D/', '', (string) ($row['cnpj'] ?? '')) ?? '';
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

        if (strlen($cnpj) !== 14 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid';
        }

        if (WorkshopProspect::query()->where('cnpj', $cnpj)->exists()) {
            return 'duplicates';
        }

        if (EmailSuppression::isSuppressed($email)) {
            return 'suppressed';
        }

        if (Workshop::query()->whereRaw('lower(email) = ?', [$email])->exists()
            || User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return 'customers';
        }

        if (WorkshopProspect::query()->where('email', $email)->exists()) {
            return 'duplicates';
        }

        WorkshopProspect::query()->create([
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
        ]);

        return 'created';
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
