<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\Maintenance\MaintenancePhotoUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin \App\Models\MaintenancePhoto */
class MaintenancePhotoResource extends JsonResource
{
    /**
     * Quem está vendo, quando não é o usuário da requisição (a linha do tempo recebe o leitor como
     * parâmetro). Nulo usa $request->user().
     */
    private ?User $viewer = null;

    /**
     * Quem monta a foto depois de já ter aplicado a regra de acesso à OS (a linha do tempo: leitor
     * com viewFullHistory, OS ocultas fora, anexos bloqueados fora) passa $authorized = true e a
     * checagem da MaintenancePhotoPolicy::view não se repete por OS (custaria uma query por OS de
     * outro tenant).
     */
    private bool $authorized = false;

    public function forViewer(?User $viewer, bool $authorized = false): static
    {
        $this->viewer = $viewer;
        $this->authorized = $authorized && $viewer !== null;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $this->viewer ?? $request->user();
        $mayOpen = $viewer !== null && $this->viewerMayOpen($request, $viewer);

        return [
            'id' => $this->id,
            'maintenance_id' => $this->maintenance_id,
            'maintenance_item_id' => $this->maintenance_item_id,
            'subject' => $this->subject,
            'stage' => $this->stage,
            'original_name' => $this->original_name,
            // Rota atrás da MaintenancePhotoPolicy::view, nunca AppStorage::url($this->path): no
            // disco público o arquivo abre sem token e, no S3/R2, a URL assinada é compartilhável.
            // O path também sai do contrato, porque com ele o cliente remonta a URL do storage.
            'url' => $this->when(
                $viewer !== null,
                fn () => route('api.maintenance-photos.show', $this->id),
            ),
            // URLs temporárias (10 min, janelas fixas) geradas só depois da MaintenancePhotoPolicy::view.
            // Foto ainda sem processar devolve a URL do original em thumb e display. Nenhum caminho
            // do storage sai daqui, só a URL.
            'variants' => $this->when(
                $mayOpen,
                fn () => MaintenancePhotoUrls::variants($this->resource),
            ),
            'expires_at' => $this->when(
                $mayOpen,
                fn () => MaintenancePhotoUrls::expiresAt()->toIso8601String(),
            ),
            'width' => $this->width,
            'height' => $this->height,
            'dominant_color' => $this->dominant_color,
            'processed' => $this->isProcessed(),
            'sort' => $this->sort,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * A MaintenancePhotoPolicy::view só depende da OS e de quem vê, então a resposta vale para todas
     * as fotos da mesma OS na requisição (uma checagem por OS, não por foto).
     */
    private function viewerMayOpen(Request $request, User $viewer): bool
    {
        if ($this->authorized) {
            return true;
        }

        $key = 'maintenance_photo_view.'.$viewer->id.'.'.$this->maintenance_id;

        if (! $request->attributes->has($key)) {
            $this->resource->loadMissing('maintenance.vehicle');

            $request->attributes->set($key, Gate::forUser($viewer)->allows('view', $this->resource));
        }

        return (bool) $request->attributes->get($key);
    }
}
