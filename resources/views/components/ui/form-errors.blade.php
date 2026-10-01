{{--
    Resumo de erros no topo do formulário, com um link para cada campo. role="alert" e
    tabindex="-1": com autofocus o foco vai para o resumo ao carregar (atributo autofocus nativo e
    data-autofocus, que resources/js/ui/form-errors.js usa de reserva). Os links levam o foco ao
    controle.

    Props:
    - bag: error bag nomeado; o padrão é o default.
    - threshold: mínimo de campos com erro para o resumo aparecer (2 por padrão; com 1 erro, a
      mensagem do próprio campo basta). Use 1 em formulários curtos com erro fora dos campos.
    - title: título; o padrão é "Revise N campo(s) antes de continuar".
    - autofocus: true (padrão) leva o foco ao resumo.
    - ids: chave do erro => id do controle, para o link. Aceita curinga ('photos.*' => 'photos').
      null ou false deixa a mensagem sem link (erro que não é de um campo, como 'credentials').
      Sem mapa, o id sai da chave como no <x-ui.field> (items.0.name vira items_0_name).
    - id: id do resumo ('resumo-erros').

    Ex.: <x-ui.form-errors :ids="['credentials' => null]" :threshold="1" />
--}}@props([
    'bag' => null,
    'threshold' => 2,
    'title' => null,
    'autofocus' => true,
    'ids' => [],
    'id' => 'resumo-erros',
])
@php
    $summaryMessages = ($errors ?? null) instanceof \Illuminate\Support\ViewErrorBag
        ? $errors->getBag(filled($bag) ? $bag : 'default')
        : new \Illuminate\Support\MessageBag;
    $summaryItems = [];

    foreach ($summaryMessages->keys() as $summaryKey) {
        $summaryTarget = \App\Support\FormField::controlId((string) $summaryKey);

        foreach ((array) $ids as $summaryPattern => $summaryTargetId) {
            if ($summaryPattern === $summaryKey || \Illuminate\Support\Str::is((string) $summaryPattern, (string) $summaryKey)) {
                $summaryTarget = filled($summaryTargetId) ? (string) $summaryTargetId : null;

                break;
            }
        }

        $summaryItems[] = ['message' => (string) $summaryMessages->first($summaryKey), 'target' => $summaryTarget];
    }

    $summaryCount = count($summaryItems);
    $summaryTitle = filled($title) ? $title : ($summaryCount === 1 ? 'Revise 1 campo antes de continuar' : "Revise {$summaryCount} campos antes de continuar");
@endphp
@if($summaryCount > 0 && $summaryCount >= max(1, (int) $threshold))
    <div {{ $attributes->class(['rounded-card border border-danger/40 bg-danger-soft p-4 text-sm text-foreground'])->merge([
        'id' => $id,
        'role' => 'alert',
        'tabindex' => '-1',
        'aria-labelledby' => $id.'-title',
        'data-slot' => 'form-errors',
        'data-autofocus' => $autofocus ? true : null,
        'autofocus' => $autofocus ? true : null,
    ]) }}>
        <div class="flex items-start gap-3">
            <x-ui.icon name="exclamation-triangle" variant="solid" class="mt-0.5 size-5 text-danger" />
            <div class="min-w-0 flex-1">
                <p id="{{ $id }}-title" class="font-semibold text-danger">{{ $summaryTitle }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 marker:text-danger">
                    @foreach($summaryItems as $summaryItem)
                        <li>
                            @if($summaryItem['target'] !== null)
                                <a href="#{{ $summaryItem['target'] }}" data-form-errors-link class="font-medium text-danger underline underline-offset-2 hover:text-danger-hover">{{ $summaryItem['message'] }}</a>
                            @else
                                {{ $summaryItem['message'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
