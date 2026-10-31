<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OwnerDecisionRequest;
use App\Http\Resources\Api\V1\WorkshopRecordResource;
use App\Models\Maintenance;
use App\Services\Maintenance\MaintenanceOwnerDecisionService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Registros que oficinas fizeram nos veículos da conta antes dela chegar (OS sem proprietário) ou,
 * sem vínculo provado, num carro que já era dela. O proprietário atual decide: vincular, aceitar
 * notas e fotos (só com propriedade verificada). Ocultar saiu do contrato: no lugar dele há o
 * dispute, que marca o registro como contestado sem tirá-lo das superfícies e não pede o CRLV-e.
 */
#[Group('Workshop records', weight: 13)]
class WorkshopRecordController extends Controller
{
    public function __construct(private readonly MaintenanceOwnerDecisionService $decisions) {}

    #[QueryParameter('status', 'pending (default) or all.')]
    public function index(Request $request): JsonResponse
    {
        $records = $this->decisions
            ->recordsFor($request->user(), onlyPending: $request->query('status', 'pending') !== 'all')
            ->with(['vehicle', 'items', 'workshop', 'verifiedWorkshop', 'invoices', 'photos'])
            ->get();

        return ApiResponse::success(WorkshopRecordResource::collection($records)->resolve($request));
    }

    public function decide(OwnerDecisionRequest $request, string $id): JsonResponse
    {
        $maintenance = Maintenance::query()->with('vehicle')->findOrFail($id);

        try {
            if ($request->boolean('hide_from_public')) {
                return ApiResponse::error(OwnerDecisionRequest::HIDE_REMOVED_MESSAGE, 422, [
                    'hide_from_public' => [OwnerDecisionRequest::HIDE_REMOVED_MESSAGE],
                ]);
            }

            $maintenance = $this->decisions->decide(
                $maintenance,
                $request->user(),
                $request->boolean('link'),
                $request->boolean('attach_files'),
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error((string) collect($exception->errors())->flatten()->first(), 422, $exception->errors());
        }

        $maintenance->load(['vehicle', 'items', 'workshop', 'verifiedWorkshop', 'invoices', 'photos']);

        return ApiResponse::success((new WorkshopRecordResource($maintenance))->resolve($request));
    }

    /**
     * O dono atual contesta um registro sem selo. Não exige propriedade verificada: ver o docblock
     * de MaintenanceOwnerDecisionService::dispute.
     */
    public function dispute(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $maintenance = Maintenance::query()->with('vehicle')->findOrFail($id);

        try {
            $maintenance = $this->decisions->dispute(
                $maintenance,
                $request->user(),
                filled($request->input('reason')) ? (string) $request->input('reason') : null,
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error((string) collect($exception->errors())->flatten()->first(), 422, $exception->errors());
        }

        return $this->respondWith($maintenance, $request);
    }

    public function withdrawDispute(Request $request, string $id): JsonResponse
    {
        $maintenance = $this->decisions->withdrawDispute(
            Maintenance::query()->with('vehicle')->findOrFail($id),
            $request->user(),
        );

        return $this->respondWith($maintenance, $request);
    }

    private function respondWith(Maintenance $maintenance, Request $request): JsonResponse
    {
        $maintenance->load(['vehicle', 'items', 'workshop', 'verifiedWorkshop', 'invoices', 'photos']);

        return ApiResponse::success((new WorkshopRecordResource($maintenance))->resolve($request));
    }
}
