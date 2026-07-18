<?php

namespace App\Services\Invoice;

use Illuminate\Http\UploadedFile;

class InvoiceParseFeedback
{
    public static function isPdf(UploadedFile|string $file): bool
    {
        if ($file instanceof UploadedFile) {
            return strtolower($file->getClientOriginalExtension()) === 'pdf';
        }

        return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
    }

    public static function unparsedPdfMessage(?string $fileName = null): string
    {
        $label = $fileName ? "\"{$fileName}\"" : 'O PDF enviado';

        return "{$label} foi salvo, mas não conseguimos extrair os itens automaticamente. "
            .'Se você tiver o XML da NF-e, envie-o no lugar do PDF — o XML é mais confiável e tolerante a variações de layout.';
    }
}
