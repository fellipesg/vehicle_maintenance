{{--
    Perfil da conta no admin, com os termos do glossário (User::typeLabel(), que lê
    App\Enums\Portal): Proprietário, Lojista e Oficina.

    Props: user (obrigatório). Atributos extras vão para o <x-ui.badge>.
    Ex.: <x-admin.user-type-badge :user="$account" />
--}}
@props([
    'user' => null,
])
@php
    \App\Support\UiProps::required('x-admin.user-type-badge', 'user', $user);

    $userTypeLabel = $user?->typeLabel() ?? \App\Enums\Portal::Owner->label();
    $userTypeVariant = match ($user?->portal()) {
        \App\Enums\Portal::Dealer => 'neutral',
        \App\Enums\Portal::Workshop => 'primary',
        default => 'info',
    };
@endphp
<x-ui.badge :variant="$userTypeVariant" {{ $attributes }}>{{ $userTypeLabel }}</x-ui.badge>@php
    // Sem quebra de linha depois do badge: o componente fica no meio do texto sem espaço sobrando.
@endphp
