{{--
    Grupo de controles com legenda: radios, lista de checkboxes, ou um bloco do formulário
    ("Dados do veículo").

    Props:
    - legend: título do grupo (ou <x-slot:legend>). legendSrOnly deixa só para leitor de tela.
    - description: texto abaixo da legenda, ligado ao grupo por aria-describedby.
    - name: name dos radios/checkboxes do slot, que o recebem por @aware. Com ele, o erro do grupo
      ($errors->first(name) ou de name.*) aparece uma vez, no fim do fieldset, em {id}-error.
    - selected: valor escolhido (ou lista, para checkboxes), repassado às opções por @aware.
    - required: asterisco na legenda + "(obrigatório)" para leitor de tela.
    - inline: opções lado a lado (Sim / Não), quebrando linha quando faltar espaço.
    - bag / error: como no <x-ui.field>.

    Ex.:
    <x-ui.fieldset name="services[]" legend="Serviços feitos" description="Marque todos que se aplicam.">
        <x-ui.checkbox value="oil" label="Troca de óleo" />
        <x-ui.checkbox value="filters" label="Filtros" />
    </x-ui.fieldset>
--}}@props([
    'legend' => null,
    'description' => null,
    'name' => null,
    'selected' => null,
    'required' => false,
    'legendSrOnly' => false,
    'inline' => false,
    'bag' => null,
    'error' => null,
])
@php
    $fieldsetId = \App\Support\FormField::controlId($name) ?? $attributes->get('id') ?? 'grupo-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $fieldsetError = filled($error) ? (string) $error : \App\Support\FormField::error($errors ?? null, $name, $bag);
    $fieldsetHasDescription = filled($description);
    $fieldsetDescribedBy = \App\Support\FormField::describedBy(
        $fieldsetHasDescription ? $fieldsetId.'-description' : null,
        $fieldsetError !== null ? $fieldsetId.'-error' : null,
    );
@endphp
<fieldset {{ $attributes->class(['min-w-0'])->merge([
    'aria-describedby' => $fieldsetDescribedBy,
    'data-slot' => 'fieldset',
    'data-invalid' => $fieldsetError !== null ? 'true' : null,
]) }}>
    @if(filled($legend))
        <legend @class(['text-sm font-semibold text-foreground', 'sr-only' => $legendSrOnly])>{{ $legend }}@if($required)<span class="ml-0.5 text-danger" aria-hidden="true">*</span><span class="sr-only"> (obrigatório)</span>@endif</legend>
    @endif
    @if($fieldsetHasDescription)
        <p id="{{ $fieldsetId }}-description" class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
    @endif
    <div @class(['mt-2', 'grid gap-y-1' => ! $inline, 'flex flex-wrap gap-x-6' => $inline]) data-slot="fieldset-content">
        {{ $slot }}
    </div>
    @if($fieldsetError !== null)
        <p id="{{ $fieldsetId }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-danger">
            <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
            <span><span class="sr-only">Erro: </span>{{ $fieldsetError }}</span>
        </p>
    @endif
</fieldset>
