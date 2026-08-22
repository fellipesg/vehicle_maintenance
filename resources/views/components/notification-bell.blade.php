@auth
    @php
        $notificationUser = auth()->user();
        $unreadNotifications = $notificationUser->unreadNotifications()->latest()->limit(8)->get();
        $unreadCount = $notificationUser->unreadNotifications()->count();
    @endphp

    <details class="group relative">
        <summary class="relative list-none cursor-pointer rounded-lg p-2 text-automotive-300 marker:content-none hover:bg-automotive-800 hover:text-wrench-400">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                <path d="M5.25 9a6.75 6.75 0 0 1 13.5 0v1.5c0 .621.504 1.125 1.125 1.125H20.1a1.125 1.125 0 0 1 1.125 1.125v1.5a1.125 1.125 0 0 1-1.125 1.125h-1.35a3.375 3.375 0 0 1-6.6 0h-1.35A1.125 1.125 0 0 1 7.65 14.25v-1.5A1.125 1.125 0 0 1 8.775 11.625H9.9c.621 0 1.125-.504 1.125-1.125V9Z"/>
            </svg>
            @if($unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-wrench-500 px-1 text-[10px] font-bold text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </summary>

        <div class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border border-automotive-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-automotive-100 px-4 py-3">
                <p class="text-sm font-semibold text-automotive-900">Notificações</p>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="text-xs text-wrench-600 hover:underline">Marcar todas como lidas</button>
                    </form>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse($unreadNotifications as $notification)
                    @php
                        $data = $notification->data;
                        $title = $data['title'] ?? 'Lembrete de revisão';
                        $body = $data['body'] ?? '';
                    @endphp
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="block border-b border-automotive-50 last:border-b-0">
                        @csrf
                        <button type="submit" class="w-full px-4 py-3 text-left transition hover:bg-automotive-50">
                            <p class="text-sm font-medium text-automotive-900">{{ $title }}</p>
                            @if($body !== '')
                                <p class="mt-1 text-xs text-automotive-600">{{ $body }}</p>
                            @endif
                            <p class="mt-1 text-[11px] text-automotive-400">{{ $notification->created_at->diffForHumans() }}</p>
                            <p class="mt-1 text-[11px] font-medium text-wrench-600">Ver veículo →</p>
                        </button>
                    </form>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-automotive-500">Nenhuma notificação nova.</p>
                @endforelse
            </div>

            <div class="border-t border-automotive-100 px-4 py-2">
                <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-wrench-600 hover:underline">Ver todas</a>
            </div>
        </div>
    </details>
@endauth
