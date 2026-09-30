{{--
    Sino da topbar: popover (<details>) com as 8 notificações não lidas mais recentes.
    - O <summary> é o botão: nome acessível "Notificações, N não lidas" (o contador visual é
      aria-hidden) e alvo de 40px.
    - Fecha com Esc (o foco volta ao sino) e com clique fora; o script fica no fim do componente.
    - No celular o painel ocupa a largura da tela abaixo da topbar (fixed inset-x-4 top-16); a partir
      de sm vira um popover de 20rem alinhado ao sino.
    - Cada item marca a notificação como lida; "Ver veículo" só aparece quando há uma ficha que o
      portal da conta pode abrir (App\Support\NotificationLink).

    Prop: unreadCount (a navbar já conta as não lidas; sem ela, o componente conta).
--}}
@props([
    'unreadCount' => null,
])

@auth
    @php
        $notificationUser = auth()->user();
        $unreadNotifications = $notificationUser->unreadNotifications()->latest()->limit(8)->get();
        $unreadCount ??= $notificationUser->unreadNotifications()->count();
        $bellLabel = match (true) {
            $unreadCount === 0 => 'Notificações',
            $unreadCount === 1 => 'Notificações, 1 não lida',
            default => "Notificações, {$unreadCount} não lidas",
        };
    @endphp

    <details class="relative" data-notification-bell>
        <summary class="relative inline-flex size-10 cursor-pointer list-none items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast marker:content-none hover:bg-surface-muted hover:text-foreground motion-reduce:transition-none [&::-webkit-details-marker]:hidden">
            <span class="sr-only">{{ $bellLabel }}</span>
            <x-ui.icon name="bell" class="size-6" />
            @if($unreadCount > 0)
                <span aria-hidden="true" data-notification-count class="absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs leading-none font-bold text-primary-foreground tabular-nums ring-2 ring-background">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @endif
        </summary>

        <div class="theme-default fixed inset-x-4 top-16 z-50 overflow-hidden rounded-card border border-border bg-surface text-foreground shadow-xl transition-[opacity,translate] duration-fast ease-smooth-out starting:-translate-y-1 starting:opacity-0 motion-reduce:transition-none sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:mt-2 sm:w-80">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-2">
                <p class="text-sm font-semibold text-foreground">Notificações</p>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="link inline-flex min-h-10 items-center text-xs">Marcar todas como lidas</button>
                    </form>
                @endif
            </div>

            <ul role="list" class="max-h-96 divide-y divide-border overflow-y-auto">
                @forelse($unreadNotifications as $notification)
                    @php
                        $notificationData = (array) $notification->data;
                        $notificationTitle = is_string($notificationData['title'] ?? null) && $notificationData['title'] !== '' ? $notificationData['title'] : 'Lembrete de revisão';
                        $notificationBody = is_string($notificationData['body'] ?? null) ? $notificationData['body'] : '';
                        $notificationVehicleUrl = \App\Support\NotificationLink::vehicleUrl($notificationData, $notificationUser);
                    @endphp
                    <li>
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-3 text-left transition-colors duration-fast hover:bg-surface-muted motion-reduce:transition-none">
                                <span class="block text-sm font-medium text-foreground">{{ $notificationTitle }}</span>
                                @if($notificationBody !== '')
                                    <span class="mt-1 block text-xs text-muted-foreground">{{ $notificationBody }}</span>
                                @endif
                                <span class="mt-1 block text-xs text-muted-foreground">
                                    <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans(['options' => \Carbon\CarbonInterface::JUST_NOW]) }}</time>
                                </span>
                                @if($notificationVehicleUrl !== null)
                                    <span class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-link">
                                        Ver veículo
                                        <x-ui.icon name="arrow-right" class="size-3.5" />
                                    </span>
                                @endif
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-muted-foreground">Nenhuma notificação nova.</li>
                @endforelse
            </ul>

            <div class="border-t border-border px-4">
                <a href="{{ route('notifications.index') }}" class="link inline-flex min-h-10 items-center text-xs">Ver todas as notificações</a>
            </div>
        </div>
    </details>

    @once
        <script>
            (() => {
                const openBells = () => document.querySelectorAll('details[data-notification-bell][open]');

                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') {
                        return;
                    }

                    openBells().forEach((bell) => {
                        const hadFocus = bell.contains(document.activeElement);

                        bell.open = false;

                        if (hadFocus) {
                            bell.querySelector('summary')?.focus();
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    openBells().forEach((bell) => {
                        if (!bell.contains(event.target)) {
                            bell.open = false;
                        }
                    });
                });
            })();
        </script>
    @endonce
@endauth
