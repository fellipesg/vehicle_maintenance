{{--
    Formulário compartilhado de modelo de mensagem (criar e editar).

    Variáveis:
    - action: URL do form.
    - method: GET (implícito) — o form usa POST + @method('PUT') no edit.
    - template: WorkshopMessageTemplate existente (edit) ou null (create).
    - isEdit: bool.
--}}
@php
    use App\Enums\WorkshopMessageTrigger;
    use App\Enums\ServiceCategory;

    $triggerOptions = WorkshopMessageTrigger::options();
    $categoryOptions = collect(ServiceCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="space-y-6">
        <x-ui.form-section title="Gatilho" description="Quando esta mensagem é disparada.">
            <x-ui.field name="trigger" label="Gatilho" required>
                <x-ui.select>
                    <option value="">Escolha o gatilho…</option>
                    @foreach($triggerOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('trigger', $template?->trigger->value) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <x-ui.field name="service_category" label="Categoria do serviço" optional hint="Deixe em branco para disparar para qualquer categoria.">
                <x-ui.select>
                    <option value="">Qualquer categoria</option>
                    @foreach($categoryOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('service_category', $template?->service_category) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </x-ui.form-section>

        <x-ui.form-section title="Conteúdo" description="Título e corpo da mensagem enviada ao cliente.">
            <x-ui.field name="title" label="Título" required>
                <x-ui.input value="{{ old('title', $template?->title) }}" maxlength="255" />
            </x-ui.field>

            <x-ui.field name="body" label="Mensagem" required>
                <x-ui.textarea rows="5" maxlength="5000" counter>{{ old('body', $template?->body) }}</x-ui.textarea>
            </x-ui.field>
        </x-ui.form-section>

        <x-ui.form-section title="Tempo de disparo" description="Quando a mensagem sai em relação ao serviço.">
            <x-ui.field name="lead_kilometers" label="Quilômetros até a próxima revisão" optional hint="Para o gatilho de revisão programada. Ex.: 1000 km antes.">
                <x-ui.input type="number" min="0" max="50000" value="{{ old('lead_kilometers', $template?->lead_kilometers) }}" />
            </x-ui.field>

            <x-ui.field name="min_days_since_service" label="Dias após o serviço" optional hint="Para o gatilho de retorno após serviço ou garantia vencendo.">
                <x-ui.input type="number" min="1" max="3650" value="{{ old('min_days_since_service', $template?->min_days_since_service) }}" />
            </x-ui.field>
        </x-ui.form-section>

        <x-ui.form-section title="Status">
            <x-ui.checkbox name="is_active" value="1" :checked="old('is_active', $template?->is_active ?? true)" label="Modelo ativo" hint="Modelos inativos não disparam mensagens." />
        </x-ui.form-section>

        <div class="flex gap-3">
            <x-ui.button type="submit">{{ $isEdit ? 'Salvar alterações' : 'Criar modelo' }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workshop.message-templates.index')">Cancelar</x-ui.button>
        </div>
    </div>
</form>
