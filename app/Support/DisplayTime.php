<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Horário mostrado às pessoas. O banco grava em UTC (config app.timezone); telas, e-mails e o PDF
 * do histórico mostram no fuso de config('app.display_timezone') (America/Sao_Paulo), para o
 * horário do selo no /v/{código} bater com o do PDF que o comprador tem em mãos.
 */
final class DisplayTime
{
    public const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    public static function timezone(): string
    {
        $timezone = config('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : self::DEFAULT_TIMEZONE;
    }

    /**
     * Cópia do instante no fuso de exibição (o original não muda). Null continua null.
     */
    public static function local(?CarbonInterface $moment): ?CarbonInterface
    {
        return $moment?->copy()->setTimezone(self::timezone());
    }

    /**
     * Agora, no fuso de exibição.
     */
    public static function now(): CarbonInterface
    {
        return now(self::timezone());
    }
}
