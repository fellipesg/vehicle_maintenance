<?php

namespace App\Services\Maintenance;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaintenanceWarrantyApplicator
{
    public function sync(Maintenance $maintenance, Request $request, ?Workshop $workshop): void
    {
        if ($workshop === null) {
            $maintenance->warranties()->delete();

            return;
        }

        $this->syncGeneralWarranty($maintenance, $request, $workshop);
        $this->syncItemWarranties($maintenance, $request, $workshop);
    }

    public function recomputeDates(Maintenance $maintenance): void
    {
        $maintenance->load('warranties');

        foreach ($maintenance->warranties as $warranty) {
            $warranty->recomputeDatesFromMaintenance();
            $warranty->save();
        }
    }

    private function syncGeneralWarranty(Maintenance $maintenance, Request $request, Workshop $workshop): void
    {
        $maintenance->warranties()->where('scope', WarrantyScope::Order)->delete();

        $templateId = $request->input('general_warranty_template_id');
        if ($templateId === null || $templateId === '') {
            return;
        }

        $template = $this->resolveTemplate($workshop, (int) $templateId, WarrantyScope::Order);
        if ($template === null) {
            return;
        }

        $warranty = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance);
        $warranty->save();
    }

    private function syncItemWarranties(Maintenance $maintenance, Request $request, Workshop $workshop): void
    {
        $maintenance->warranties()->where('scope', WarrantyScope::Item)->delete();

        $requestItems = $this->normalizedRequestItems($request);
        $items = $maintenance->items()->orderBy('id')->get();

        if ($items->count() !== count($requestItems)) {
            throw ValidationException::withMessages([
                'items' => 'Não foi possível vincular garantias aos itens. Salve novamente a ordem de serviço.',
            ]);
        }

        foreach ($items as $index => $item) {
            $templateId = $requestItems[$index]['warranty_template_id'] ?? null;
            if ($templateId === null || $templateId === '') {
                continue;
            }

            $template = $this->resolveTemplate($workshop, (int) $templateId, WarrantyScope::Item);
            if ($template === null) {
                continue;
            }

            $warranty = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance, $item);
            $warranty->save();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedRequestItems(Request $request): array
    {
        $items = $request->input('items', []);
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item) && ! empty($item['name']);
        }));
    }

    private function resolveTemplate(Workshop $workshop, int $templateId, WarrantyScope $scope): ?WarrantyTemplate
    {
        return WarrantyTemplate::query()
            ->where('workshop_id', $workshop->id)
            ->where('scope', $scope)
            ->where('is_active', true)
            ->find($templateId);
    }
}
