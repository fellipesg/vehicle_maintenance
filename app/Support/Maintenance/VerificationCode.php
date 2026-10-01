<?php

namespace App\Support\Maintenance;

/**
 * Código do Selo da oficina (RVL-XXXX-XX, gerado por MaintenanceVerificationStamper) digitado à mão
 * em /verificar ou numa URL /v/{código}. Quem copia do PDF impresso escreve em minúsculas, sem os
 * hífens, com espaços ou sem o prefixo "RVL": tudo isso vira a forma canônica.
 */
final class VerificationCode
{
    public const PREFIX = 'RVL';

    /**
     * Formato mostrado nas dicas e mensagens de erro.
     */
    public const FORMAT_HINT = 'RVL-XXXX-XX';

    /**
     * "rvl test 12", "RVLTEST12" e "TEST-12" viram "RVL-TEST-12". O que não tem 6 letras ou números
     * depois do prefixo devolve null.
     */
    public static function normalize(?string $input): ?string
    {
        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $input) ?? '');

        if (strlen($compact) === strlen(self::PREFIX) + 6 && str_starts_with($compact, self::PREFIX)) {
            $compact = substr($compact, strlen(self::PREFIX));
        }

        if (strlen($compact) !== 6) {
            return null;
        }

        return self::PREFIX.'-'.substr($compact, 0, 4).'-'.substr($compact, 4);
    }
}
