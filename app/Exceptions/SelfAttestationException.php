<?php

namespace App\Exceptions;

use LogicException;

/**
 * Alguém tentou emitir Selo da oficina (ou laudo) sobre um veículo que o tenant da própria oficina
 * possui. É `LogicException` de propósito: todo caminho de emissão pergunta antes
 * (`App\Support\Vehicle\IssuerIndependence`) e trata a recusa com o que faz sentido ali — procedência
 * declarada, selo não aplicado, 422 na tela. Chegar até aqui significa caminho novo que esqueceu de
 * perguntar, e aí falhar alto é melhor que gravar um selo que mente.
 */
class SelfAttestationException extends LogicException
{
    public static function forVehicle(int $workshopId, ?int $vehicleId): self
    {
        return new self(
            "A oficina {$workshopId} não pode atestar sobre o veículo {$vehicleId}: o veículo é do tenant dela."
        );
    }
}
