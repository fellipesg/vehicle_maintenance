<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $this->findOwnedNotification($request, $notificationId);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $vehicleId = data_get($notification->data, 'vehicle_id');
        if (is_numeric($vehicleId)) {
            return redirect()->route($this->vehicleShowRoute($request), (int) $vehicleId);
        }

        $url = data_get($notification->data, 'vehicle_url');

        if (is_string($url) && str_starts_with($url, '/')) {
            return redirect($url);
        }

        if (is_string($url) && $url !== '') {
            return redirect()->to($url);
        }

        return redirect()->route($this->dashboardRoute($request));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route($this->dashboardRoute($request))
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

    private function dashboardRoute(Request $request): string
    {
        return match ($request->user()->user_type) {
            'garage' => 'garage.dashboard',
            'workshop' => 'workshop.dashboard',
            default => 'user.dashboard',
        };
    }

    private function vehicleShowRoute(Request $request): string
    {
        return match ($request->user()->user_type) {
            'garage' => 'garage.vehicles.show',
            default => 'user.vehicles.show',
        };
    }
}
