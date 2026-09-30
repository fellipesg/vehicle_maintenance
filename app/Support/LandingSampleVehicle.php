<?php

namespace App\Support;

use App\Support\Vehicle\VehicleIdentifierMask;

/**
 * Veículo de exemplo dos mocks da landing (hero, busca e PDF), num lugar só para os três contarem
 * a mesma história. Regras em .ai/rules/landing-views.md: serviços do mais antigo para o mais novo,
 * quilometragem sempre subindo, um ponto de procedência por serviço, na mesma ordem, e Placa atual,
 * Chassi e RENAVAM com valores de aparência real (nunca "2022 · chassi · RENAVAM").
 */
class LandingSampleVehicle
{
    public const MODEL = 'Honda Civic EX';

    public const YEAR = 2022;

    public const PLATE = 'ABC1D23';

    public const CHASSIS = '93HFB1640NZ004251';

    public const RENAVAM = '00384719256';

    public const WORKSHOP = 'Silva Auto';

    /**
     * Serviços em ordem cronológica: o mais antigo primeiro e o mais recente por último.
     *
     * @return list<array{title: string, sealed: bool, author: string, date: string, km: int, invoice: bool}>
     */
    public static function events(): array
    {
        return [
            ['title' => 'Revisão 40 mil', 'sealed' => true, 'author' => self::WORKSHOP, 'date' => '18/03/2026', 'km' => 40012, 'invoice' => true],
            ['title' => 'Pastilhas dianteiras', 'sealed' => false, 'author' => 'proprietário', 'date' => '03/06/2026', 'km' => 40580, 'invoice' => false],
            ['title' => 'Alinhamento e balanceamento', 'sealed' => true, 'author' => self::WORKSHOP, 'date' => '22/07/2026', 'km' => 41240, 'invoice' => false],
            ['title' => 'Troca de óleo 5W30', 'sealed' => true, 'author' => self::WORKSHOP, 'date' => '12/08/2026', 'km' => 42180, 'invoice' => true],
        ];
    }

    public static function sealedCount(): int
    {
        return count(array_filter(self::events(), fn (array $event): bool => $event['sealed']));
    }

    public static function declaredCount(): int
    {
        return count(self::events()) - self::sealedCount();
    }

    /**
     * Resumo de procedência no formato do produto: "3 com selo · 1 declarada".
     */
    public static function provenanceSummary(): string
    {
        $declared = self::declaredCount();

        return self::sealedCount().' com selo · '.$declared.' '.($declared === 1 ? 'declarada' : 'declaradas');
    }

    /**
     * Quilometragem no formato brasileiro: 40012 vira "40.012 km".
     */
    public static function kilometers(int $kilometers): string
    {
        return number_format($kilometers, 0, ',', '.').' km';
    }

    /**
     * Chassi como a busca mostra para quem não é o dono (VehicleIdentifierMask).
     */
    public static function maskedChassis(): string
    {
        return (string) VehicleIdentifierMask::chassis(self::CHASSIS);
    }

    public static function maskedRenavam(): string
    {
        return (string) VehicleIdentifierMask::renavam(self::RENAVAM);
    }
}
