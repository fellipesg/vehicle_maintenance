<?php

namespace App\Support;

/**
 * CPF ou CNPJ parcial para mostrar na tela sem expor o número inteiro (proprietário lido do CRLV-e,
 * documento da conta do lojista). O pedaço visível basta para conferir com o documento em mãos.
 */
final class DocumentMask
{
    private const MASK_CHARACTER = '•';

    /**
     * "374.528.458-54" vira "•••.528.458-••"; "12.345.678/0001-90" vira "12.345.678/••••-••". Outro
     * tamanho mostra só os 2 últimos dígitos. Vazio ou sem dígitos devolve null.
     */
    public static function cpfOrCnpj(?string $document): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $document) ?? '';

        if ($digits === '') {
            return null;
        }

        $mask = self::MASK_CHARACTER;

        return match (strlen($digits)) {
            11 => "{$mask}{$mask}{$mask}.".substr($digits, 3, 3).'.'.substr($digits, 6, 3)."-{$mask}{$mask}",
            14 => substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3)."/{$mask}{$mask}{$mask}{$mask}-{$mask}{$mask}",
            default => str_repeat($mask, max(0, strlen($digits) - 2)).substr($digits, -2),
        };
    }
}
