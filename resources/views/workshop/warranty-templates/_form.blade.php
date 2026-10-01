{{--
    Campos do modelo de garantia (Novo modelo e Editar modelo).

    Bloqueio: enquanto a oficina tem garantias vigentes, termo, prazo e escopo de um modelo que já
    existe não mudam (ValidatesWarrantyTemplateImmutability). Os campos ficam readonly (dá para
    selecionar e copiar o texto) e só o status continua editável; "Duplicar" cria um modelo novo
    com o mesmo conteúdo.

    Variáveis: $workshop, $template (na edição), $templatesLocked e $source (modelo duplicado, no
    Novo modelo).
--}}
@php
    use App\Enums\WarrantyScope;

    $template = $template ?? null;
    $source = $source ?? null;
    $readonly = ($templatesLocked ?? false) && $template !== null;
    $scopeOptions = [
        WarrantyScope::Order->value => 'Garantia geral da OS',
        WarrantyScope::Item->value => 'Garantia de cada peça (item da OS)',
    ];
    $base = $template ?? $source;
    $nameValue = $template?->name ?? ($source ? 'Cópia de '.$source->name : null);
    $scopeValue = $base?->scope?->value ?? WarrantyScope::Order->value;
    $readonlyClass = 'read-only:bg-surface-muted read-only:text-foreground';
@endphp

<div class="space-y-6">
    @if($readonly)
        <x-ui.alert variant="warning" role="status" title="Termo, prazo e escopo bloqueados" data-template-locked>
            A oficina tem garantias vigentes emitidas com os modelos atuais, então o conteúdo deste modelo não muda. Você pode ativar ou desativar o modelo, ou duplicá-lo para escrever um termo novo.
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" icon="document-duplicate" :href="route('workshop.warranty-templates.create', ['duplicar' => $template->id])">Duplicar como novo modelo</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @endif

    <x-ui.form-section id="secao-termo" title="Termo de garantia" description="O cliente vê o nome e o texto do termo na OS e no PDF do histórico.">
        <x-ui.field name="name" label="Nome do modelo" hint="Ex.: Garantia de 90 dias em serviços de freio." required>
            <x-ui.input :value="$nameValue" required maxlength="255" autocomplete="off" :readonly="$readonly" class="{{ $readonlyClass }}" />
        </x-ui.field>

        <x-ui.field name="body" label="Texto do termo" hint="O que a garantia cobre, o que não cobre e como o cliente aciona." required>
            <x-ui.textarea :value="$base?->body" rows="8" required autosize :readonly="$readonly" class="{{ $readonlyClass }}" />
        </x-ui.field>
    </x-ui.form-section>

    <x-ui.form-section id="secao-prazo" title="Prazo e escopo" description="A validade é contada a partir da data do serviço na OS.">
        <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
            <x-ui.field name="duration_days" label="Duração" required>
                <x-ui.input-group :class="$readonly ? 'bg-surface-muted' : null">
                    <x-ui.input type="number" :value="$base?->duration_days ?? 90" min="1" max="3650" step="1" inputmode="numeric" required :readonly="$readonly" class="tabular-nums" />
                    <x-slot:trailing>dias</x-slot:trailing>
                </x-ui.input-group>
            </x-ui.field>

            @if($readonly)
                <x-ui.field label="Escopo" hint="Não muda enquanto houver garantias vigentes.">
                    <x-ui.input id="scope" :value="$scopeOptions[$scopeValue] ?? $scopeValue" readonly class="{{ $readonlyClass }}" />
                </x-ui.field>
            @else
                <x-ui.field name="scope" label="Escopo" hint="Geral: uma garantia para a OS inteira. Por peça: escolhida em cada item." required>
                    <x-ui.select :options="$scopeOptions" :value="$scopeValue" required />
                </x-ui.field>
            @endif
        </div>
    </x-ui.form-section>

    <x-ui.form-section id="secao-status" title="Status">
        <x-ui.switch name="is_active" label="Modelo ativo" :checked="(bool) ($template?->is_active ?? true)"
                     description="Ativo: aparece ao registrar uma OS. Inativo: sai das próximas OS, sem mudar as garantias já emitidas." />
    </x-ui.form-section>
</div>
