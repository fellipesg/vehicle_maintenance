{{-- Lista de modelos de mensagem automática da oficina. --}}
@extends('layouts.app')

@section('title', 'Mensagens')

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header title="Mensagens"
                          description="Mensagens automáticas disparadas para clientes depois do serviço, como lembretes de revisão e retorno pós-serviço.">
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('workshop.message-templates.create')">Novo modelo</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="space-y-6">
            @if($templates->isEmpty())
                <x-ui.empty-state icon="envelope" heading-level="h2" title="Nenhum modelo de mensagem ainda"
                                  description="Configure mensagens automáticas para manter o contato com os clientes após os serviços.">
                    <x-slot:actions>
                        <x-ui.button icon="plus" :href="route('workshop.message-templates.create')">Criar primeiro modelo</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <ul role="list" class="space-y-3">
                    @foreach($templates as $template)
                        @php
                            $dispatches = (int) ($template->dispatches_count ?? 0);
                        @endphp
                        <li class="rounded-card border border-border bg-surface p-4 shadow-sm sm:p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-semibold text-foreground">{{ $template->title }}</h2>
                                        @if($template->is_active)
                                            <x-ui.badge variant="success" dot>Ativo</x-ui.badge>
                                        @else
                                            <x-ui.badge dot>Inativo</x-ui.badge>
                                        @endif
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ $template->trigger->label() }}
                                        · {{ $dispatches === 1 ? '1 disparo' : $dispatches.' disparos' }}
                                    </p>
                                    <p class="line-clamp-2 max-w-prose text-sm text-muted-foreground">{{ $template->body }}</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                                    <x-ui.button variant="ghost" size="sm" :href="route('workshop.message-templates.edit', $template)">Editar</x-ui.button>
                                    <form method="POST" action="{{ route('workshop.message-templates.destroy', $template) }}"
                                          data-confirm="Excluir este modelo de mensagem? Esta ação não pode ser desfeita."
                                          data-confirm-action-label="Excluir">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">Excluir</x-ui.button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if($templates->hasPages())
                    <div class="pt-2">{{ $templates->links() }}</div>
                @endif
            @endif
        </div>
    </x-ui.container>
@endsection
