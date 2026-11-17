<?php

namespace App\Enums;

/**
 * Resultado de uma vistoria cautelar (vehicle_inspections.verdict). É o que o laudo afirma, e o que
 * o comprador lê primeiro — por isso é coluna, não texto livre: tem de dar para filtrar, desenhar e
 * responder "este carro foi reprovado?" sem interpretar prosa.
 */
enum InspectionVerdict: string
{
    case Approved = 'approved';
    case ApprovedWithRestrictions = 'approved_with_restrictions';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Aprovado',
            self::ApprovedWithRestrictions => 'Aprovado com restrições',
            self::Rejected => 'Reprovado',
        };
    }

    /**
     * Variante do <x-ui.badge>.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::ApprovedWithRestrictions => 'warning',
            self::Rejected => 'danger',
        };
    }

    /**
     * Veredito que pode conviver com um eixo reprovado.
     *
     * Só "Aprovado" não pode: é a única afirmação que *esconde* a reprovação de quem compra. As
     * outras duas já avisam, então a incoerência que sobra ("com restrições" sem restrição nenhuma)
     * prejudica quem vende, não quem compra, e o vistoriador assina o que quiser — ver
     * VehicleInspectionService::assertVerdictMatchesFindings.
     */
    public function toleratesFailedFindings(): bool
    {
        return $this !== self::Approved;
    }

    /**
     * Rótulo de um valor gravado no banco, ou null quando está vazio ou é desconhecido.
     */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value)?->label();
    }

    /**
     * Valores aceitos na validação: Rule::in(InspectionVerdict::values()).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $verdict): string => $verdict->value, self::cases());
    }

    /**
     * Opções para <x-ui.select :options>, na ordem do formulário.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $verdict) {
            $options[$verdict->value] = $verdict->label();
        }

        return $options;
    }
}
