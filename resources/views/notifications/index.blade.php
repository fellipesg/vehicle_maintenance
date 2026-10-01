@extends('layouts.app')

@section('title', 'Notificações')

@section('content')
<x-ui.container size="md" padded>
    <x-ui.page-header title="Notificações" description="Lembretes de revisão e avisos da sua conta.">
        @if($unreadCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="check" class="max-sm:w-full">Marcar todas como lidas</x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if($notifications->isEmpty())
        <x-ui.empty-state
            icon="bell"
            heading-level="h2"
            title="Nenhuma notificação ainda"
            description="Lembretes de revisão e avisos da sua conta aparecem aqui."
        />
    @else
        <ul role="list" class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface shadow-xs" data-notification-list>
            @foreach($notifications as $notification)
                @php
                    $notificationData = (array) $notification->data;
                    $notificationTitle = is_string($notificationData['title'] ?? null) && $notificationData['title'] !== '' ? $notificationData['title'] : 'Notificação';
                    $notificationBody = is_string($notificationData['body'] ?? null) ? $notificationData['body'] : '';
                    $notificationIsUnread = $notification->read_at === null;
                    $notificationVehicleUrl = \App\Support\NotificationLink::vehicleUrl($notificationData, auth()->user());
                    // Não lida: botão que marca como lida (e abre o veículo, se houver). Lida: link
                    // para o veículo, ou só o texto.
                    $notificationTag = $notificationIsUnread ? 'button' : ($notificationVehicleUrl !== null ? 'a' : 'div');
                @endphp
                <li @if($notificationIsUnread) data-unread @endif>
                    @if($notificationIsUnread)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                    @endif
                    <{{ $notificationTag }}
                        @if($notificationTag === 'button') type="submit" @endif
                        @if($notificationTag === 'a') href="{{ $notificationVehicleUrl }}" @endif
                        @class([
                            'flex w-full items-start gap-3 px-4 py-4 text-left',
                            'transition-colors duration-fast hover:bg-surface-muted motion-reduce:transition-none' => $notificationTag !== 'div',
                            'bg-accent' => $notificationIsUnread,
                        ])
                    >
                        <span aria-hidden="true" @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-primary' => $notificationIsUnread])></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-foreground">
                                @if($notificationIsUnread)<span class="sr-only">Não lida: </span>@endif{{ $notificationTitle }}
                            </span>
                            @if($notificationBody !== '')
                                <span class="mt-1 block text-sm text-muted-foreground">{{ $notificationBody }}</span>
                            @endif
                            <span class="mt-2 block text-xs text-muted-foreground">
                                <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ \App\Support\DisplayTime::local($notification->created_at)->format('d/m/Y H:i') }}</time>
                            </span>
                            @if($notificationVehicleUrl !== null)
                                <span class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-link">
                                    Ver veículo
                                    <x-ui.icon name="arrow-right" class="size-3.5" />
                                </span>
                            @elseif($notificationIsUnread)
                                <span class="mt-1 block text-xs font-medium text-link">Marcar como lida</span>
                            @endif
                        </span>
                    </{{ $notificationTag }}>
                    @if($notificationIsUnread)
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($notifications->hasPages())
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    @endif
</x-ui.container>
@endsection
