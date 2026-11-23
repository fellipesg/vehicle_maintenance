<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClaimVehicleWithCrlvRequest;
use App\Http\Requests\Api\V1\ReadCrlvRequest;
use App\Http\Requests\Api\V1\VehicleCrlvMessages;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Services\Crlv\CrlvApiImportStore;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvImportFailureReporter;
use App\Services\Crlv\CrlvParseException;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\Maintenance\OwnerlessMaintenanceService;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Cadastro pelo CRLV-e no app, o mesmo assistente do site (App\Http\Controllers\Web\Concerns\
 * ImportsVehicleFromCrlv) sem sessão. A leitura decide o caminho, como lá:
 *
 * - `new`: veículo que não está na RevisaLog. O app preenche o formulário com o que foi lido e grava
 *   em POST /api/v1/vehicles com o `crlv_verification_token`, que marca a posse como verificada.
 * - `claim`: veículo que já está na RevisaLog. O app mostra o que encontrou e confirma em
 *   POST /api/v1/vehicles/crlv/claim.
 * - `linked`: a conta já é dona atual dele; não há o que gravar.
 *
 * Procuração (CRLV-e fora do CPF/CNPJ da conta, quando é exigida) só existe no site: `ownership`
 * vem `consignment` e a gravação responde 422 apontando o site.
 */
#[Group('Vehicles', weight: 10)]
class VehicleCrlvController extends Controller
{
    #[Endpoint(title: 'Read a CRLV-e PDF', description: 'Reads the CRLV-e PDF and says whether it is a new vehicle (save it with POST /vehicles and the crlv_verification_token) or one already registered (confirm with POST /vehicles/crlv/claim). The reading is kept for 30 minutes.')]
    public function read(
        ReadCrlvRequest $request,
        CrlvPdfParser $parser,
        CrlvExerciseValidator $exerciseValidator,
        CrlvApiImportStore $store,
        VehicleOwnershipService $ownership,
    ): JsonResponse {
        try {
            $crlv = $parser->parseUpload($request->file('crlv'));
            $exerciseValidator->assertAcceptable($crlv->exerciseYear);
        } catch (RuntimeException $exception) {
            if ($exception instanceof CrlvParseException) {
                app(CrlvImportFailureReporter::class)->report($exception, $request->file('crlv'), $request->user(), 'api.vehicles.crlv');
            }

            return ApiResponse::error($exception->getMessage(), 422, ['crlv' => [$exception->getMessage()]]);
        }

        $user = $request->user();
        $existing = VehicleOwnershipService::findExistingVehicle($crlv);
        $alreadyLinked = $existing !== null && Gate::forUser($user)->allows('update', $existing);

        $mode = match (true) {
            $existing === null => 'new',
            $alreadyLinked => 'linked',
            default => 'claim',
        };

        $ownershipType = $existing === null
            ? $ownership->resolveOwnershipType($user, $crlv)
            : $ownership->resolveClaimOwnershipType($user, $existing, $crlv);

        return ApiResponse::success([
            'mode' => $mode,
            'ownership' => $alreadyLinked ? 'owner' : $ownershipType,
            'crlv_verification_token' => $store->put($user, $crlv),
            'expires_in_minutes' => CrlvApiImportStore::TTL_MINUTES,
            'vehicle' => $this->readFields($crlv),
            'existing_vehicle' => $existing !== null ? $this->existingVehicleSummary($existing) : null,
        ]);
    }

    #[Endpoint(title: 'Claim a registered vehicle with the CRLV-e', description: 'Links the vehicle found by POST /vehicles/crlv to the account, with verified ownership.')]
    public function claim(
        ClaimVehicleWithCrlvRequest $request,
        CrlvApiImportStore $store,
        VehicleOwnershipService $ownership,
        OwnerlessMaintenanceService $ownerless,
    ): JsonResponse {
        $user = $request->user();
        $token = $request->validated('crlv_verification_token');
        $crlv = $store->find($user, $token);
        $vehicle = $crlv !== null ? VehicleOwnershipService::findExistingVehicle($crlv) : null;

        if ($vehicle === null) {
            return ApiResponse::error(VehicleCrlvMessages::EXPIRED, 422, ['crlv_verification_token' => [VehicleCrlvMessages::EXPIRED]]);
        }

        try {
            if ($ownership->resolveClaimOwnershipType($user, $vehicle, $crlv) === 'consignment') {
                throw new RuntimeException('consignment_required');
            }

            $vehicle = $ownership->claimExisting($user, $vehicle, $crlv);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage() === 'consignment_required'
                ? VehicleCrlvMessages::POWER_OF_ATTORNEY_ON_WEB
                : $exception->getMessage();

            return ApiResponse::error($message, 422, ['vehicle' => [$message]]);
        }

        $store->forget($user, $token);

        return ApiResponse::success([
            'vehicle' => new VehicleResource($vehicle->fresh()),
            'pending_workshop_records_count' => $user->isGarage() ? 0 : $ownerless->pendingCountForVehicle($vehicle),
        ], 'Vehicle linked successfully');
    }

    /**
     * O que o app usa para preencher o formulário. Sem o titular (nome e CPF/CNPJ): o app não mostra
     * e não precisa deles, e a decisão de posse é do servidor.
     *
     * @return array<string, mixed>
     */
    private function readFields(CrlvParseResult $crlv): array
    {
        return [
            ...$crlv->toFormData(),
            'brand_raw' => $crlv->brandRaw,
            'model_raw' => $crlv->modelRaw,
            'brand_matched' => $crlv->brandMatched,
            'model_matched' => $crlv->modelMatched,
            'detran_state' => $crlv->detranState,
            'fuel' => $crlv->fuel,
            'exercise_year' => $crlv->exerciseYear,
            'manufacturing_year' => $crlv->manufacturingYear,
        ];
    }

    /**
     * O veículo encontrado, sem identificadores: quem enviou o CRLV-e ainda não provou que é dono.
     *
     * @return array<string, mixed>
     */
    private function existingVehicleSummary(Vehicle $vehicle): array
    {
        $vehicle->loadCount([
            'maintenances',
            'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
            'maintenances as awaiting_decision_maintenances_count' => fn ($query) => $query->where('owner_status', Maintenance::OWNER_PENDING),
        ]);

        return [
            'id' => $vehicle->id,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'maintenances_count' => $vehicle->maintenances_count,
            'verified_maintenances_count' => $vehicle->verified_maintenances_count,
            'awaiting_decision_maintenances_count' => $vehicle->awaiting_decision_maintenances_count,
        ];
    }
}
