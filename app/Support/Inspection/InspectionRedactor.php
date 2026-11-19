<?php

namespace App\Support\Inspection;

use App\Enums\InspectionFinding;
use App\Models\User;
use App\Models\VehicleInspection;
use Illuminate\Support\Collection;

/**
 * Forma mínima de um laudo para quem não tem o histórico daquele veículo — na prática, o visitante
 * da consulta pública e da página /laudo/{código}.
 *
 * O que sai: o resumo do vistoriador, as observações de cada eixo e o PDF. São texto livre, e texto
 * livre de vistoria descreve o carro de alguém com o detalhe que a LGPD cobra — e, como todo campo
 * aberto do produto, carrega a chance de ter entrado nome, CPF ou telefone do cliente.
 *
 * O que **fica**: veredito, data, hodômetro lido, validade, emissor, código de conferência e o
 * **status de cada eixo**. É o que torna o laudo útil a quem está comprando — "Reprovado: chassi e
 * numeração do motor" sem a prosa — e é a mesma direção das outras superfícies: o comprador fica com
 * mais informação, não menos (ver .ai/rules/public-lookup.md).
 *
 * Trabalha numa cópia em memória (clone): nunca grava.
 */
final class InspectionRedactor
{
    public static function redact(VehicleInspection $inspection, ?User $viewer): VehicleInspection
    {
        if ($inspection->showsFullReportTo($viewer)) {
            return $inspection;
        }

        $copy = clone $inspection;
        $copy->setAttribute('summary', null);
        $copy->setAttribute('report_path', null);
        $copy->setAttribute('report_original_name', null);
        $copy->setAttribute('owner_dispute_reason', null);
        $copy->setAttribute('findings', self::statusesOnly($inspection->findings));

        return $copy;
    }

    /**
     * Lista redigida, já **sem** os laudos que este leitor não pode ver.
     *
     * O reject vem antes do map de propósito, e é o mesmo de
     * App\Support\Maintenance\MaintenanceRedactor::redactAll: quem monta uma listagem passa a coleção
     * por aqui e não precisa lembrar de filtrar o suprimido pela equipe. Esquecer o filtro e
     * confiar só na redação deixaria o laudo suprimido aparecer na forma mínima, que é justamente o
     * que a supressão existe para impedir — e a oficina emissora continua vendo o próprio
     * (VehicleInspection::hiddenFrom).
     *
     * @param  iterable<int, VehicleInspection>  $inspections
     * @return Collection<int, VehicleInspection>
     */
    public static function redactAll(iterable $inspections, ?User $viewer): Collection
    {
        return Collection::make($inspections)
            ->reject(fn (VehicleInspection $inspection): bool => $inspection->hiddenFrom($viewer))
            ->map(fn (VehicleInspection $inspection): VehicleInspection => self::redact($inspection, $viewer))
            ->values();
    }

    /**
     * Mapa completo, com os status e sem as observações.
     *
     * @return array<string, array{status: string, note: null}>
     */
    private static function statusesOnly(mixed $findings): array
    {
        $statuses = [];

        foreach (InspectionFinding::cases() as $axis) {
            $statuses[$axis->value] = [
                'status' => InspectionFindings::statusOf($findings, $axis)->value,
                'note' => null,
            ];
        }

        return $statuses;
    }
}
