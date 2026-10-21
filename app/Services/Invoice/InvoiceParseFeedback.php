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

    /**
     * Falha ao anexar uma nota depois de a OS já estar gravada. Diz o que não entrou e,
     * acima de tudo, que a OS não precisa ser cadastrada de novo: era o reenvio do
     * formulário depois do erro 500 que criava a OS duplicada.
     *
     * @param  list<string>  $fileNames
     */
    public static function notAttachedMessage(array $fileNames): string
    {
        $names = array_values(array_filter(
            $fileNames,
            fn (string $name): bool => trim($name) !== '',
        ));

        if (count($names) <= 1) {
            $label = $names === [] ? 'A nota fiscal enviada' : "A nota fiscal \"{$names[0]}\"";

            return "{$label} não foi anexada: houve uma falha ao salvar o arquivo. "
                .'O resto da OS foi gravado — não cadastre a OS de novo, basta enviar essa nota outra vez.';
        }

        $quoted = implode(', ', array_map(fn (string $name): string => "\"{$name}\"", $names));

        return "Estas notas fiscais não foram anexadas por uma falha ao salvar: {$quoted}. "
            .'O resto da OS foi gravado — não cadastre a OS de novo, basta enviar essas notas outra vez.';
    }
}
