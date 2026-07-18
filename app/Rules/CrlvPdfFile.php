<?php

namespace App\Rules;

use App\Services\Crlv\CrlvPdfParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class CrlvPdfFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Arquivo CRLV-e inválido.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if ($extension !== 'pdf') {
            $fail('Apenas arquivos PDF do CRLV-e digital são aceitos.');

            return;
        }

        $path = $value->getRealPath() ?: $value->path();

        if (! app(CrlvPdfParser::class)->isCrlvDocument($path)) {
            $fail('O PDF enviado não é um CRLV-e digital reconhecido. Exporte o documento pelo app Carteira Digital de Trânsito (CDT).');
        }
    }
}
