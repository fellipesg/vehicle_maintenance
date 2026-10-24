<?php

namespace App\Support\Maintenance;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Forma mínima de uma OS que a oficina registrou num carro sem proprietário (LGPD). Enquanto o
 * proprietário não vinculou (ou depois que recusou), o histórico mostra só data, quilometragem,
 * categoria/tipo, nomes dos itens, oficina e selo: sem descrição livre, valores nem garantias.
 * Notas e fotos só aparecem depois do aceite. Só a oficina autora vê tudo.
 *
 * Também tira as fotos de quem não tem as fotos das manutenções ligadas (MaintenancePhotoAccess),
 * em qualquer OS. A única exceção é a consulta pública por placa ($publicLookup), que mostra as
 * fotos públicas (veículo, depois) a qualquer visitante, com ou sem o recurso.
 *
 * Trabalha numa cópia em memória (clone): nunca grava, e as telas e resources continuam lendo as
 * relações de sempre.
 */
final class MaintenanceRedactor
{
    public static function redact(Maintenance $maintenance, ?User $viewer, bool $publicLookup = false): Maintenance
    {
        $isOwnerless = $maintenance->isOwnerlessRecord();
        $hideDetails = $isOwnerless && $maintenance->hidesDetailsFrom($viewer);
        $hideAttachments = $isOwnerless && $maintenance->hidesAttachmentsFrom($viewer);
        $hidePhotos = ! $publicLookup && ! MaintenancePhotoAccess::allows($viewer);

        if (! $hideDetails && ! $hideAttachments && ! $hidePhotos) {
            return $maintenance;
        }

        $copy = clone $maintenance;

        if ($hideDetails) {
            $copy->setAttribute('description', null);
            $copy->loadMissing('items');
            $copy->setRelation('items', $copy->items->map(function ($item) {
                $item = clone $item;
                $item->setAttribute('description', null);
                $item->setAttribute('unit_price', null);
                $item->setAttribute('total_price', null);
                $item->setAttribute('part_number', null);
                $item->setAttribute('has_warranty', false);
                $item->setAttribute('warranty_starts_at', null);
                $item->setAttribute('warranty_ends_at', null);
                $item->setRelation('warranty', null);

                return $item;
            })->values());
            $copy->setRelation('generalWarranty', null);
            $copy->setRelation('warranties', new Collection);
        }

        if ($hideAttachments) {
            $copy->setRelation('invoices', new Collection);
            $copy->setAttribute('invoices_count', 0);
        }

        if ($hideAttachments || $hidePhotos) {
            $copy->setRelation('photos', new Collection);
            $copy->setAttribute('photos_count', 0);
        }

        return $copy;
    }

    /**
     * @param  iterable<int, Maintenance>  $maintenances
     * @return Collection<int, Maintenance>
     */
    public static function redactAll(iterable $maintenances, ?User $viewer, bool $publicLookup = false): Collection
    {
        return Collection::make($maintenances)
            ->reject(fn (Maintenance $maintenance): bool => $maintenance->hiddenFrom($viewer))
            ->map(fn (Maintenance $maintenance): Maintenance => self::redact($maintenance, $viewer, $publicLookup))
            ->values();
    }
}
