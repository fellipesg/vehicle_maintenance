<?php

namespace App\Support\Vehicle;

/**
 * Máscara do chassi e do RENAVAM para quem vê o veículo sem ser o dono (busca pública, lojista que
 * ainda não tem o carro). Mantém o começo do chassi (fabricante, WMI) e o fim de cada número, o
 * bastante para conferir com o documento em mãos, sem expor o identificador inteiro.
 */
final class VehicleIdentifierMask
{
    private const MASK_CHARACTER = '•';

    /**
     * Tamanho do chassi atual (VIN, a partir de 1990). Os anteriores têm de 9 a 16 caracteres
     * (app/Rules/Chassis.php).
     */
    private const VIN_LENGTH = 17;

    /**
     * No chassi mais curto, no máximo 40% dos caracteres ficam à mostra, e só no fim.
     */
    private const SHORT_CHASSIS_MAX_VISIBLE_RATIO = 0.4;

    /**
     * VIN: "9BWZZZ377VT004251" vira "9BW••••••••••4251", 3 primeiros e 4 últimos caracteres.
     *
     * Chassi anterior a 1990, mais curto: só o fim, com no máximo 40% à mostra (até 4 caracteres).
     * "BA1234567" vira "••••••567". Com 3 + 4 à mostra, um chassi de 9 caracteres deixaria só 2
     * escondidos, e a busca pública (igualdade exata pelo chassi) acharia o resto por tentativa.
     */
    public static function chassis(?string $chassis): ?string
    {
        $length = mb_strlen(trim((string) $chassis));

        if ($length >= self::VIN_LENGTH) {
            return self::mask($chassis, visiblePrefix: 3, visibleSuffix: 4);
        }

        $visibleSuffix = min(4, (int) floor($length * self::SHORT_CHASSIS_MAX_VISIBLE_RATIO));

        return self::mask($chassis, visiblePrefix: 0, visibleSuffix: $visibleSuffix);
    }

    /**
     * "12345678901" vira "•••••••8901": só os 4 últimos dígitos.
     */
    public static function renavam(?string $renavam): ?string
    {
        return self::mask($renavam, visiblePrefix: 0, visibleSuffix: 4);
    }

    private static function mask(?string $value, int $visiblePrefix, int $visibleSuffix): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if ($length === 0) {
            return null;
        }

        // Número curto demais para esconder o meio: esconde tudo menos o último caractere.
        if ($length <= $visiblePrefix + $visibleSuffix) {
            return str_repeat(self::MASK_CHARACTER, max(0, $length - 1)).mb_substr($value, -1);
        }

        return mb_substr($value, 0, $visiblePrefix)
            .str_repeat(self::MASK_CHARACTER, $length - $visiblePrefix - $visibleSuffix)
            .($visibleSuffix > 0 ? mb_substr($value, -$visibleSuffix) : '');
    }
}
