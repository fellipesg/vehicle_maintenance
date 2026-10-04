<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notificações da conta (as mesmas do sino do portal web), para a lista do app.
 */
#[Group('Notifications', weight: 26)]
class NotificationController extends Controller
{
    use ResolvesPagination;

    /**
     * Lista paginada, mais recentes primeiro. ?unread=1 traz só as não lidas.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($notifications, NotificationResource::class);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->first();

        if ($record === null) {
            return ApiResponse::error('Notificação não encontrada.', 404);
        }

        $record->markAsRead();

        return ApiResponse::success(new NotificationResource($record), 'Notificação marcada como lida.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['unread_count' => 0], 'Notificações marcadas como lidas.');
    }
}
