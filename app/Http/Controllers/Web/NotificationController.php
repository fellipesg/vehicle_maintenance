<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\NotificationLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->latest()->paginate(20),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca como lida e abre a ficha do veículo citado no portal da conta. Sem ficha para abrir
     * (notificação sem veículo ou conta de oficina), volta para a página de onde veio.
     */
    public function markAsRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $this->findOwnedNotification($request, $notificationId);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $vehicleUrl = NotificationLink::vehicleUrl($notification, $request->user());

        if ($vehicleUrl !== null) {
            return redirect($vehicleUrl);
        }

        return redirect()->back(fallback: route($request->user()->portal()->dashboardRoute()));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back(fallback: route($request->user()->portal()->dashboardRoute()))
            ->with('success', 'Notificações marcadas como lidas.');
    }

    private function findOwnedNotification(Request $request, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        return $notification;
    }
}
