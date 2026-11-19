<?php

namespace App\Enums;

/**
 * Situação de um eixo de achado dentro do laudo (ver App\Enums\InspectionFinding).
 *
 * `NotAssessed` é o padrão de propósito: vistoria que não olhou um eixo diz isso, em vez de o
 * silêncio virar "sem apontamento". Um laudo que afirma mais do que examinou é pior que um laudo
 * incompleto.
 */
enum InspectionFindingStatus: string
{
    case NotAssessed = 'not_assessed';
    case Ok = 'ok';
    case Attention = 'attention';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::NotAssessed => 'Não avaliado',
            self::Ok => 'Sem apontamento',
            self::Attention => 'Ponto de atenção',
            self::Fail => 'Reprovado',
        };
    }

    /**
     * Variante do <x-ui.badge>.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::NotAssessed => 'neutral',
            self::Ok => 'success',
            self::Attention => 'warning',
            self::Fail => 'danger',
        };
    }

    /**
     * Afirmação positiva sobre o eixo: "olhei e está bem". É a única que a leitura de hodômetro
     * abaixo do já registrado contradiz (VehicleInspectionService).
     */
    public function assertsNothingWrong(): bool
    {
        return $this === self::Ok;
    }

    /**
     * Eixo que o comprador precisa ver mesmo na forma mínima (InspectionRedactor).
     */
    public function isReportable(): bool
    {
        return $this === self::Attention || $this === self::Fail;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
