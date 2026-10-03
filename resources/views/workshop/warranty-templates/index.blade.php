{{--
    Modelos de garantia da oficina. Cada modelo mostra escopo, prazo, status (liga/desliga direto
    na lista, sem abrir a edição) e em quantas OS já foi usado. Modelo usado não pode ser
    excluído: o botão fica visível, inativo, com a explicação na dica. O aviso de bloqueio
    (garantias vigentes) aparece uma vez, no topo.
--}}
@extends('layouts.app')

@section('title', 'Modelos de garantia')

@php
    use App\Enums\WarrantyScope;

    $scopeLabels = [
        WarrantyScope::Order->value => 'Garantia geral da OS',
        WarrantyScope::Item->value => 'Garantia por peça',
    ];
@endphp

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header title="Modelos de garantia"
                          description="Termos que você anexa às OS: a garantia geral do serviço ou a de cada peça. O cliente vê o termo e a data de validade.">
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('workshop.warranty-templates.create')">Novo modelo</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="space-y-6">
            @error('template')
                <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
            @enderror

            @if($templatesLocked && $templates->isNotEmpty())
                <x-ui.alert variant="warning" role="status" title="Termo, prazo e escopo bloqueados enquanto houver garantias vigentes" data-templates-locked>
                    Há garantias vigentes emitidas pela oficina, então o conteúdo dos modelos atuais não muda. Você ainda pode ativar ou desativar qualquer modelo, e "Duplicar" cria um modelo novo com o texto para você ajustar.
                </x-ui.alert>
            @endif

            @if($templates->isEmpty())
                <x-ui.empty-state icon="shield-check" heading-level="h2" title="Nenhum modelo de garantia ainda"
                                  description="Um modelo guarda o termo e o prazo da garantia. Ao registrar a OS você escolhe o modelo, e a validade é calculada a partir da data do serviço." data-templates-empty>
                    <x-slot:actions>
                        <x-ui.button icon="plus" :href="route('workshop.warranty-templates.create')">Criar primeiro modelo</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <ul role="list" class="space-y-3">
                    @foreach($templates as $template)
                        @php
                            $usedIn = (int) ($template->maintenances_using_count ?? 0);
                            $usedLabel = $usedIn === 1 ? 'Usado em 1 OS' : "Usado em {$usedIn} OS";
                        @endphp
                        <li class="rounded-card border border-border bg-surface p-4 shadow-sm sm:p-5" data-template="{{ $template->id }}">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <h2 class="text-base font-semibold text-foreground">{{ $template->name }}</h2>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                        <x-ui.badge>{{ $scopeLabels[$template->scope->value] ?? $template->scope->value }}</x-ui.badge>
                                        <x-ui.badge>{{ $template->duration_days }} {{ (int) $template->duration_days === 1 ? 'dia' : 'dias' }}</x-ui.badge>
                                        <x-ui.badge :variant="$template->is_active ? 'success' : 'neutral'" dot data-template-status>{{ $template->is_active ? 'Ativo' : 'Inativo' }}</x-ui.badge>
                                        @if($usedIn > 0)
                                            <span class="text-sm text-muted-foreground">{{ $usedLabel }}</span>
                                        @endif
                                    </div>
                                    @if(filled($template->body))
                                        <p class="mt-2 line-clamp-2 max-w-prose text-sm text-muted-foreground">{{ $template->body }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                                    <form method="POST" action="{{ route('workshop.warranty-templates.update', $template) }}" data-template-toggle>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $template->is_active ? '0' : '1' }}">
                                        <button type="submit" role="switch" aria-checked="{{ $template->is_active ? 'true' : 'false' }}"
                                                class="inline-flex min-h-10 items-center gap-2 rounded-control px-2 text-sm font-medium text-foreground transition-colors duration-fast ease-smooth-out hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none">
                                            <span @class(['relative inline-flex h-6 w-11 shrink-0 items-center rounded-full', 'bg-accent-foreground' => $template->is_active, 'bg-input' => ! $template->is_active]) aria-hidden="true">
                                                <span @class(['block size-5 rounded-full bg-surface shadow-sm', 'translate-x-5.5' => $template->is_active, 'translate-x-0.5' => ! $template->is_active])></span>
                                            </span>
                                            <span>Ativo<span class="sr-only">: modelo {{ $template->name }}</span></span>
                                        </button>
                                    </form>

                                    <x-ui.button variant="secondary" size="sm" icon="pencil-square" :href="route('workshop.warranty-templates.edit', $template)">
                                        {{ $templatesLocked ? 'Ver' : 'Editar' }}<span class="sr-only"> o modelo {{ $template->name }}</span>
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" icon="document-duplicate" :href="route('workshop.warranty-templates.create', ['duplicar' => $template->id])">
                                        Duplicar<span class="sr-only"> o modelo {{ $template->name }}</span>
                                    </x-ui.button>

                                    @if($usedIn > 0)
                                        <x-ui.tooltip :text="$usedLabel.': não pode ser excluído. Desative para tirar das próximas OS.'">
                                            <button type="button" aria-disabled="true" data-template-delete-blocked
                                                    class="inline-flex min-h-10 cursor-not-allowed items-center gap-2 rounded-control px-3 text-sm font-semibold text-muted-foreground opacity-70 sm:min-h-8">
                                                <x-ui.icon name="lock-closed" class="size-4" />
                                                Excluir<span class="sr-only"> o modelo {{ $template->name }}</span>
                                            </button>
                                        </x-ui.tooltip>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('workshop.warranty-templates.destroy', $template) }}"
                                            data-confirm="O modelo {{ $template->name }} deixa de aparecer ao registrar uma OS. Não é possível desfazer."
                                            data-confirm-title="Excluir o modelo de garantia?"
                                            data-confirm-action-label="Excluir modelo"
                                            data-confirm-variant="danger"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost" size="sm" icon="trash" class="text-danger hover:bg-danger-soft">Excluir<span class="sr-only"> o modelo {{ $template->name }}</span></x-ui.button>
                                        </form>
                                    @endif
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
