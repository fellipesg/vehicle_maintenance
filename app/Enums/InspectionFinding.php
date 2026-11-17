<?php

namespace App\Enums;

/**
 * Eixos de achado de uma vistoria cautelar: as perguntas que todo laudo responde, uma a uma, cada
 * uma com um App\Enums\InspectionFindingStatus e uma observação
 * (vehicle_inspections.findings, montado por App\Support\Inspection\InspectionFindings).
 *
 * São eixos, não uma lista livre, porque é o que permite ao comprador comparar dois laudos e ao
 * produto dizer "reprovado por remarcação de chassi" sem ler prosa. A lista é uma só: formulário,
 * laudo público, forma mínima e PDF leem daqui.
 */
enum InspectionFinding: string
{
    case Structural = 'structural';
    case Paint = 'paint';
    case Records = 'records';
    case Identification = 'identification';
    case Recall = 'recall';
    case Odometer = 'odometer';
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';

    public function label(): string
    {
        return match ($this) {
            self::Structural => 'Estrutura e monobloco',
            self::Paint => 'Pintura e indícios de reparo',
            self::Records => 'Sinistro, leilão e restrições',
            self::Identification => 'Chassi e numeração do motor',
            self::Recall => 'Recall pendente',
            self::Odometer => 'Hodômetro',
            self::Mechanical => 'Mecânica',
            self::Electrical => 'Elétrica',
        };
    }

    /**
     * O que o vistoriador procura naquele eixo, mostrado como dica no formulário.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::Structural => 'Avaria estrutural, solda fora do padrão de fábrica, desalinhamento de longarina.',
            self::Paint => 'Repintura, massa, diferença de tonalidade e outros sinais de reparo não declarado.',
            self::Records => 'Registro de sinistro, passagem por leilão, alienação, gravame e restrição judicial.',
            self::Identification => 'Remarcação ou adulteração do chassi, do motor e das etiquetas de identificação.',
            self::Recall => 'Campanha de recall aberta e não atendida.',
            self::Odometer => 'Indício de adulteração do hodômetro, inclusive leitura abaixo de registro anterior.',
            self::Mechanical => 'Motor, câmbio, suspensão e freios no que a vistoria alcança.',
            self::Electrical => 'Injeção, módulos, iluminação e acessórios elétricos.',
        };
    }

    /**
     * Valores aceitos na validação: Rule::in(InspectionFinding::values()).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $finding): string => $finding->value, self::cases());
    }

    /**
     * Rótulos na ordem do laudo.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $finding) {
            $options[$finding->value] = $finding->label();
        }

        return $options;
    }
}
