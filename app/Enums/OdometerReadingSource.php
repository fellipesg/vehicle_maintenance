<?php

namespace App\Enums;

/**
 * De onde veio a leitura do odômetro. A distinção não é documental: ela decide o que pode
 * alimentar cálculo derivado.
 *
 * `Maintenance` com selo de oficina aprovada é evidência; `OwnerReport` é autodeclaração do dono;
 * `Registration` é autodeclaração no cadastro; `Inspection` é o laudo da vistoria cautelar, que pode
 * estar relatando adulteração e por isso nunca conta para o hodômetro do veículo
 * (ver .ai/rules/inspections.md).
 *
 * Autodeclaração entra no histórico e move o hodômetro, mas nunca amplia o teto de plausibilidade
 * que a valida — é o defeito registrado em .ai/rules/models.md.
 */
enum OdometerReadingSource: string
{
    case Registration = 'registration';
    case Maintenance = 'maintenance';
    case OwnerReport = 'owner_report';
    case Inspection = 'inspection';

    /**
     * A leitura entra no hodômetro do veículo por padrão? O laudo não entra.
     */
    public function countsTowardOdometerByDefault(): bool
    {
        return $this !== self::Inspection;
    }
}
