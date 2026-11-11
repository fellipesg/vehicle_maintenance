<?php

namespace App\Services\Maintenance;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceWarranty;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaintenanceWarrantyApplicator
{
    /**
     * Apply the warranty templates chosen on the OS form.
     *
     * An issued warranty is kept as is when the request keeps its template (or
     * leaves the field out), even if that template was deactivated later, so a
     * trivial edit never drops a warranty already promised to the customer.
     * New links only accept active templates of the workshop; when the template asked
     * for no longer resolves and there is an issued warranty to lose, the request is
     * refused with 422 instead of leaving the OS with no warranty (refuseWhenIssued).
     */
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
        if (! $request->has('general_warranty_template_id')) {
            return;
        }

        $issued = $maintenance->warranties()->where('scope', WarrantyScope::Order)->first();
        $requestedTemplateId = $this->requestedTemplateId($request->input('general_warranty_template_id'));

        if ($this->keepsIssuedWarranty($issued, $requestedTemplateId)) {
            return;
        }

        // Resolver antes de apagar: ver refuseWhenIssued.
        $template = $requestedTemplateId === null
            ? null
            : $this->resolveTemplate($workshop, $requestedTemplateId, WarrantyScope::Order);

        if ($requestedTemplateId !== null && $template === null) {
            $this->refuseWhenIssued($issued, 'general_warranty_template_id');

            return;
        }

        $issued?->delete();

        if ($template === null) {
            return;
        }

        $warranty = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance);
        $warranty->save();
    }

    private function syncItemWarranties(Maintenance $maintenance, Request $request, Workshop $workshop): void
    {
        $requestItems = $this->normalizedRequestItems($request);
        $items = $maintenance->items()->with('warranty')->orderBy('id')->get();

        if ($items->count() !== count($requestItems)) {
            throw ValidationException::withMessages([
                'items' => 'Não foi possível vincular garantias aos itens. Salve novamente a ordem de serviço.',
            ]);
        }

        $pairs = $this->pairRowsWithItems($requestItems, $items);

        // Duas passadas, e a primeira não grava nada: resolver todos os modelos pedidos antes de
        // apagar qualquer garantia emitida (ver refuseWhenIssued). Resolver linha por linha deixava
        // a OS pela metade quando a última linha recusava. As duas passadas continuam valendo agora
        // que os dois update rodam em transação: elas garantem que a recusa sobe antes de qualquer
        // delete, então o applicator não depende de quem o chama ter aberto transação.
        //
        // A chave é o id do item; null planejado = encerrar a garantia do item, chave ausente =
        // não mexer na dele.
        $planned = [];

        foreach ($pairs as [$row, $item]) {
            if (! array_key_exists('warranty_template_id', $row)) {
                continue;
            }

            $issued = $item->warranty;
            $requestedTemplateId = $this->requestedTemplateId($row['warranty_template_id']);

            if ($this->keepsIssuedWarranty($issued, $requestedTemplateId)) {
                continue;
            }

            if ($requestedTemplateId === null) {
                $planned[$item->id] = null;

                continue;
            }

            $template = $this->resolveTemplate($workshop, $requestedTemplateId, WarrantyScope::Item);

            if ($template === null) {
                $this->refuseWhenIssued($issued, 'items');

                continue;
            }

            $planned[$item->id] = $template;
        }

        $maintenance->warranties()
            ->where('scope', WarrantyScope::Item)
            ->where(function ($query) use ($items) {
                $query->whereNull('maintenance_item_id')
                    ->orWhereNotIn('maintenance_item_id', $items->modelKeys());
            })
            ->delete();

        foreach ($pairs as [$row, $item]) {
            if (! array_key_exists($item->id, $planned)) {
                continue;
            }

            $item->warranty?->delete();

            $template = $planned[$item->id];

            if ($template === null) {
                continue;
            }

            $warranty = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance, $item);
            $warranty->save();
        }
    }

    /**
     * Match each submitted row with its item: rows that carry the id of an item
     * of this OS get that item; the other rows take the remaining items in
     * creation order, which is the order the rows were saved in.
     *
     * @param  list<array<string, mixed>>  $requestItems
     * @param  Collection<int, MaintenanceItem>  $items
     * @return list<array{0: array<string, mixed>, 1: MaintenanceItem}>
     */
    private function pairRowsWithItems(array $requestItems, Collection $items): array
    {
        $itemsById = $items->keyBy('id');
        $claimedIds = [];

        foreach ($requestItems as $index => $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : null;

            if ($itemId !== null && $itemsById->has($itemId) && ! in_array($itemId, $claimedIds, true)) {
                $claimedIds[$index] = $itemId;
            }
        }

        $unclaimedItems = $items
            ->reject(fn (MaintenanceItem $item) => in_array($item->id, $claimedIds, true))
            ->values()
            ->all();

        $pairs = [];

        foreach ($requestItems as $index => $row) {
            $item = isset($claimedIds[$index])
                ? $itemsById->get($claimedIds[$index])
                : array_shift($unclaimedItems);

            if ($item !== null) {
                $pairs[] = [$row, $item];
            }
        }

        return $pairs;
    }

    private function keepsIssuedWarranty(?MaintenanceWarranty $issued, ?int $requestedTemplateId): bool
    {
        if ($issued === null) {
            return false;
        }

        $issuedTemplateId = $issued->warranty_template_id === null ? null : (int) $issued->warranty_template_id;

        return $issuedTemplateId === $requestedTemplateId;
    }

    private function requestedTemplateId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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

    /**
     * Modelo pedido que não serve mais (desativado entre abrir o formulário e salvar, apagado, ou de
     * outra oficina): 422 quando há garantia emitida para perder, silêncio quando não há.
     *
     * Antes daqui o código apagava a garantia emitida e só então resolvia o modelo, então um modelo
     * que não resolvia deixava a OS **sem garantia nenhuma**, com "OS atualizada." e sem erro.
     * Medido: OS com a garantia do modelo A, a oficina desativa o modelo B em outra aba, salva a OS
     * com B selecionado — a garantia de A ia embora. Garantia é promessa ao cliente: encerrar uma só
     * pode acontecer por escolha explícita e válida ("Sem garantia", ou outro modelo que existe),
     * nunca por efeito colateral de uma página velha.
     *
     * Sem nada emitido, o pedido impossível continua sendo ignorado em silêncio e a OS nasce (ou
     * continua) sem garantia — é o que `test_deactivated_general_template_cannot_be_linked_to_a_new_os`
     * descreve, e recusar ali derrubaria a criação inteira da OS, na web e na API. Mudar isso é
     * decisão de produto, não desta correção.
     */
    private function refuseWhenIssued(?MaintenanceWarranty $issued, string $errorKey): void
    {
        if ($issued === null) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => 'O modelo de garantia escolhido não está mais disponível. Recarregue a página e escolha de novo; a garantia já emitida foi mantida.',
        ]);
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
