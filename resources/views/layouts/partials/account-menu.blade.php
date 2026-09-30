{{--
    Menu de conta (avatar ▾) da topbar, em <x-ui.dropdown> (HSDropdown do Preline: setas, Esc e
    clique fora; o foco volta ao avatar). Reúne o que antes ficava solto na barra: nome, perfil e
    Sair.

    Ordem: cabeçalho (nome, e-mail e chip do perfil) · Minha conta · Notificações · Trocar de área
    (só para quem tem mais de uma área, ou seja, admin) · Ajuda e contato · Termos e privacidade ·
    Sair (POST).

    "Minha conta" só aparece quando a rota account.edit existe.

    Variáveis opcionais: $shellPortal (área atual; sem ela, Portal::current()),
    $unreadNotificationsCount e $accountMenuPlacement (bottom-end, o padrão; top-start serve para um
    rodapé de sidebar).
--}}
@php
    $accountMenuUser = auth()->user();
    $accountMenuPortal = $shellPortal ?? \App\Enums\Portal::current($accountMenuUser);
    $accountMenuUnread = $unreadNotificationsCount ?? $accountMenuUser->unreadNotifications()->count();
    $accountMenuSwitches = collect(\App\Enums\Portal::accessibleBy($accountMenuUser))
        ->reject(fn (\App\Enums\Portal $portal): bool => $portal === $accountMenuPortal)
        ->filter(fn (\App\Enums\Portal $portal): bool => \Illuminate\Support\Facades\Route::has($portal->dashboardRoute()))
        ->values();
@endphp
<x-ui.dropdown
    id="menu-conta"
    :label="'Conta de '.$accountMenuUser->name"
    width="lg"
    :placement="$accountMenuPlacement ?? 'bottom-end'"
    data-account-menu
>
    <x-slot:trigger class="inline-flex size-10 items-center justify-center rounded-full transition-shadow duration-fast hover:ring-2 hover:ring-border-strong motion-reduce:transition-none">
        <x-ui.avatar :name="$accountMenuUser->name" :src="$accountMenuUser->avatar_url" size="sm" />
    </x-slot:trigger>

    <x-ui.dropdown-item heading data-account-menu-header>
        <span class="block truncate text-sm font-semibold text-foreground">{{ $accountMenuUser->name }}</span>
        <span class="mt-0.5 block truncate">{{ $accountMenuUser->email }}</span>
        <x-ui.badge variant="primary" size="sm" class="mt-2">{{ $accountMenuPortal->label() }}</x-ui.badge>
    </x-ui.dropdown-item>

    <x-ui.dropdown-item separator />

    @if(\Illuminate\Support\Facades\Route::has('account.edit'))
        <x-ui.dropdown-item :href="route('account.edit')" icon="user-circle">Minha conta</x-ui.dropdown-item>
    @endif
    <x-ui.dropdown-item :href="route('notifications.index')" icon="bell">
        Notificações
        @if($accountMenuUnread > 0)
            <span class="ms-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 align-middle text-xs font-bold text-primary-foreground tabular-nums">{{ $accountMenuUnread > 99 ? '99+' : $accountMenuUnread }}<span class="sr-only"> {{ $accountMenuUnread === 1 ? 'não lida' : 'não lidas' }}</span></span>
        @endif
    </x-ui.dropdown-item>

    @if($accountMenuSwitches->isNotEmpty())
        <x-ui.dropdown-item separator />
        <x-ui.dropdown-item heading>Trocar de área</x-ui.dropdown-item>
        @foreach($accountMenuSwitches as $accountMenuSwitch)
            <x-ui.dropdown-item
                :href="route($accountMenuSwitch->dashboardRoute())"
                :icon="$accountMenuSwitch === \App\Enums\Portal::Admin ? 'cog-6-tooth' : 'home'"
                :data-area-switch="$accountMenuSwitch->value"
            >{{ $accountMenuSwitch->switchLabel() }}</x-ui.dropdown-item>
        @endforeach
    @endif

    <x-ui.dropdown-item separator />
    <x-ui.dropdown-item :href="route('contact.show')" icon="question-mark-circle">Ajuda e contato</x-ui.dropdown-item>
    <x-ui.dropdown-item :href="route('legal.terms')" icon="document-text">Termos e privacidade</x-ui.dropdown-item>

    <x-ui.dropdown-item separator />
    <x-ui.dropdown-item :action="route('logout')" icon="arrow-right-start-on-rectangle">Sair</x-ui.dropdown-item>
</x-ui.dropdown>
