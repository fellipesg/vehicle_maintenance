<?php

namespace App\Support\Maintenance;

/**
 * Código curto de conferência pública, no formato PREFIXO-XXXX-XX, digitado à mão em /verificar ou
 * numa URL. Quem copia do papel escreve em minúsculas, sem os hífens, com espaços ou sem o
 * prefixo: tudo isso vira a forma canônica.
 *
 * Dois prefixos existem, porque são duas afirmações diferentes:
 *  - RVL, o Selo da oficina (maintenances.verification_code): "eu fiz este serviço";
 *  - LDO, o laudo de vistoria cautelar (vehicle_inspections.verification_code): "eu examinei e
 *    atesto o que encontrei".
 *
 * Um código **sem** prefixo é ambíguo entre os dois: `normalize` devolve a forma canônica do prefixo
 * que o chamador pediu, e quem resolve um código digitado tenta os dois, na ordem de `prefixes()`.
 */
final class VerificationCode
{
    public const PREFIX = 'RVL';

    public const INSPECTION_PREFIX = 'LDO';

    /**
     * Formato mostrado nas dicas e mensagens de erro do Selo da oficina.
     */
    public const FORMAT_HINT = 'RVL-XXXX-XX';

    /**
     * Sem I, O, 0 e 1: o código é lido de papel e de QR impresso.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * "rvl test 12", "RVLTEST12" e "TEST-12" viram "RVL-TEST-12". O que não tem 6 letras ou números
     * depois do prefixo devolve null. Um código com o prefixo do *outro* tipo também devolve null,
     * porque o prefixo errado não é só formatação: é outro documento.
     */
    public static function normalize(?string $input, string $prefix = self::PREFIX): ?string
    {
        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $input) ?? '');

        if (strlen($compact) === strlen($prefix) + 6 && str_starts_with($compact, $prefix)) {
            $compact = substr($compact, strlen($prefix));
        }

        if (strlen($compact) !== 6) {
            return null;
        }

        return $prefix.'-'.substr($compact, 0, 4).'-'.substr($compact, 4);
    }

    /**
     * Prefixos conhecidos, na ordem em que um código digitado é resolvido.
     *
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return [self::PREFIX, self::INSPECTION_PREFIX];
    }

    public static function formatHint(string $prefix = self::PREFIX): string
    {
        return $prefix.'-XXXX-XX';
    }

    /**
     * Um código novo, sem garantia de unicidade: quem grava confere na própria tabela e tenta de
     * novo (MaintenanceVerificationStamper, VehicleInspectionService).
     */
    public static function random(string $prefix = self::PREFIX): string
    {
        return $prefix.'-'.self::randomSegment(4).'-'.self::randomSegment(2);
    }

    private static function randomSegment(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
