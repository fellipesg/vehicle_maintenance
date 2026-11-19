<?php

namespace App\Features;

/**
 * Vistoria cautelar: emitir laudo (App\Models\VehicleInspection).
 *
 * Desligada para todo mundo por padrão. Aprovação de oficina é cadastro conferido — ela diz "esta
 * empresa existe e é quem diz ser" —, e não diz que a empresa atesta a condição de um veículo de
 * terceiro. No Brasil a cautelar é feita por empresa especializada, então quem emite é escolha de um
 * humano, oficina por oficina, e não consequência de estar aprovada.
 *
 * Quem pergunta é App\Support\Inspection\InspectionAccess, nunca o Pennant direto, para o escopo não
 * divergir (.ai/rules/features.md).
 */
class VehicleInspections
{
    /**
     * Nome gravado na tabela features (estável, não muda se a classe mudar de namespace).
     */
    public string $name = 'vehicle-inspections';

    /**
     * Desligada até um admin ligar. Ninguém ganha a funcionalidade por existir.
     */
    public function resolve(mixed $scope): bool
    {
        return false;
    }
}
