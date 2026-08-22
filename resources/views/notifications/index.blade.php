@extends('layouts.app')

@section('title', 'Notificações')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-automotive-900">Notificações</h1>
            <p class="mt-1 text-sm text-automotive-600">Lembretes de revisão e avisos do sistema</p>
        </div>

        @if(auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn-secondary !py-2 !text-sm">Marcar todas como lidas</button>
            </form>
        @endif
    </div>

    <div class="card divide-y divide-automotive-100 !p-0">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data;
                $title = $data['title'] ?? 'Notificação';
                $body = $data['body'] ?? '';
                $isUnread = $notification->read_at === null;
                $vehicleUrl = is_string($data['vehicle_url'] ?? null) ? $data['vehicle_url'] : null;
                $vehicleId = $data['vehicle_id'] ?? null;
                $vehicleLink = is_numeric($vehicleId)
                    ? route(auth()->user()->isGarage() ? 'garage.vehicles.show' : 'user.vehicles.show', (int) $vehicleId, absolute: false)
                    : $vehicleUrl;
            @endphp
            @if($isUnread)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="block">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-3 px-4 py-4 text-left transition hover:bg-automotive-50 {{ $isUnread ? 'bg-wrench-50/40' : '' }}">
                        <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-wrench-500"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-automotive-900">{{ $title }}</p>
                            @if($body !== '')
                                <p class="mt-1 text-sm text-automotive-600">{{ $body }}</p>
                            @endif
                            <p class="mt-2 text-xs text-automotive-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                            <p class="mt-1 text-xs font-medium text-wrench-600">Ver veículo →</p>
                        </div>
                    </button>
                </form>
            @elseif($vehicleLink)
                <a href="{{ $vehicleLink }}" class="flex items-start gap-3 px-4 py-4 transition hover:bg-automotive-50">
                    <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-transparent"></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-automotive-900">{{ $title }}</p>
                        @if($body !== '')
                            <p class="mt-1 text-sm text-automotive-600">{{ $body }}</p>
                        @endif
                        <p class="mt-2 text-xs text-automotive-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                        <p class="mt-1 text-xs font-medium text-wrench-600">Ver veículo →</p>
                    </div>
                </a>
            @else
                <div class="flex items-start gap-3 px-4 py-4">
                    <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-transparent"></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-automotive-900">{{ $title }}</p>
                        @if($body !== '')
                            <p class="mt-1 text-sm text-automotive-600">{{ $body }}</p>
                        @endif
                        <p class="mt-2 text-xs text-automotive-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            @endif
        @empty
            <p class="px-4 py-10 text-center text-sm text-automotive-500">Nenhuma notificação ainda.</p>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
