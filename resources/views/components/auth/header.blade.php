{{--
    Cabeçalho das telas de entrada (layout guest): ícone do perfil ou da tarefa, e o único H1 da
    página pelo <x-ui.page-header>, igual ao @section('title') da view.

    Props:
    - title: obrigatório (o H1).
    - description: uma frase abaixo do título.
    - icon: nome do x-ui.icon no quadro acima do título (user, building-storefront, key...).

    Slot padrão: vai para o page-header, abaixo da descrição.

    Ex.: <x-auth.header icon="key" title="Esqueci minha senha" description="Enviamos um link..." />
--}}
@props([
    'title' => null,
    'description' => null,
    'icon' => null,
])
<div {{ $attributes->merge(['data-slot' => 'auth-header']) }}>
    @if(filled($icon))
        <span class="mb-4 inline-flex size-12 items-center justify-center rounded-card border border-border-strong bg-surface-muted/50 text-accent-foreground" aria-hidden="true">
            <x-ui.icon :name="$icon" class="size-6" />
        </span>
    @endif
    <x-ui.page-header :title="$title" :description="$description">{{ $slot }}</x-ui.page-header>
</div>
