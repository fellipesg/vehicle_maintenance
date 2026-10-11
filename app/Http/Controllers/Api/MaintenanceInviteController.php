<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InviteEmailRequest;
use App\Http\Requests\Api\V1\InviteWhatsappRequest;
use App\Models\Maintenance;
use App\Services\Maintenance\MaintenanceInviteService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * "Avisar o cliente" pela API: a oficina que fez a OS convida o cliente de um carro sem proprietário.
 */
#[Group('Maintenance invites', weight: 15)]
class MaintenanceInviteController extends Controller
{
    private const GENERIC_EMAIL_ERROR = 'Não foi possível enviar para este e-mail.';

    public function __construct(private readonly MaintenanceInviteService $invites) {}

    public function email(InviteEmailRequest $request, string $id): JsonResponse
    {
        $maintenance = $this->invitable($request->user()->workshop, $id);
        $result = $this->invites->sendEmail($maintenance, $request->user()->workshop, (string) $request->validated('email'));

        return match ($result['status']) {
            MaintenanceInviteService::SENT => ApiResponse::success(
                ['email_invited_at' => $result['invite']->email_invited_at?->toIso8601String()],
                'Convite enviado por e-mail.',
                202,
            ),
            MaintenanceInviteService::ALREADY_INVITED => ApiResponse::error('Esta OS já tem um convite por e-mail enviado.', 409),
            MaintenanceInviteService::DAILY_LIMIT => ApiResponse::error('Limite diário de convites por e-mail atingido. Tente de novo amanhã.', 429),
            default => ApiResponse::error(self::GENERIC_EMAIL_ERROR, 422, ['email' => [self::GENERIC_EMAIL_ERROR]]),
        };
    }

    public function whatsapp(InviteWhatsappRequest $request, string $id): JsonResponse
    {
        $maintenance = $this->invitable($request->user()->workshop, $id);
        $result = $this->invites->whatsappUrl($maintenance, $request->user()->workshop, (string) $request->validated('phone'));

        if ($result === null) {
            $message = 'Telefone inválido. Digite o DDD e o número.';

            return ApiResponse::error($message, 422, ['phone' => [$message]]);
        }

        return ApiResponse::success([
            'url' => $result['url'],
            'whatsapp_invited_at' => $result['whatsapp_invited_at']->toIso8601String(),
        ]);
    }

    private function invitable(?\App\Models\Workshop $workshop, string $id): Maintenance
    {
        $maintenance = Maintenance::query()->with(['vehicle', 'workshop', 'verifiedWorkshop'])->findOrFail($id);

        abort_unless($workshop !== null && $this->invites->canInvite($maintenance, $workshop), 403);

        return $maintenance;
    }
}
