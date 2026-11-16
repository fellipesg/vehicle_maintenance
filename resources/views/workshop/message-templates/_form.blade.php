{{--
    Formulário compartilhado de modelo de mensagem (criar e editar).

    Variáveis:
    - action: URL do form.
    - method: GET (implícito) — o form usa POST + @method('PUT') no edit.
    - template: WorkshopMessageTemplate existente (edit) ou null (create).
    - isEdit: bool.
    - workshop: a oficina da conta autenticada, nunca outra — as opções de gatilho saem do plano
      dela (WorkshopPlanLimits::triggerOptions).
--}}
@php
    use App\Enums\ServiceCategory;
    use App\Support\Workshop\WorkshopPlanLimits;
    use App\Support\WorkshopMessageTemplateRenderer;

    // Os gatilhos com rodada no dispatcher, recortados pelo plano da oficina: escolher um sem
    // rodada criava modelo que nunca manda mensagem (WorkshopMessageTrigger::isDispatchable), e
    // oferecer um fora do plano daria uma opção que a validação recusa. O gatilho que o modelo já
    // tem entra mesmo fora do plano, senão o edit abriria em branco (WorkshopPlanLimits::triggerOptions).
    // A chave literal é montada aqui, não no echo: o Blade fecha o `{{` no primeiro `}}` que
    // encontra, então `{{ '{{'.$tag.'}}' }}` compila quebrado ("Unclosed '(' does not match '}'").
    $tagExamples = [];
    foreach (WorkshopMessageTemplateRenderer::TAGS as $tagName => $tagMeaning) {
        $tagExamples['{'.'{'.$tagName.'}'.'}'] = $tagMeaning;
    }

    $triggerOptions = WorkshopPlanLimits::triggerOptions($workshop, $template);
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

            {{--
                As sete variáveis que o renderer troca (WorkshopMessageTemplateRenderer::TAGS), que é
                a mesma lista que App\Rules\KnownMessageTemplateTags cobra no envio. Sem isto em tela
                a oficina inventava o nome e o strtr entregava "{{cliente}}" literal ao cliente.
            --}}
            <x-ui.alert variant="info" title="Variáveis que você pode usar no título e na mensagem">
                <p>Escreva exatamente como está na lista, sem espaço dentro das chaves.</p>
                <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2" data-template-tags>
                    @foreach($tagExamples as $tagExample => $tagMeaning)
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <dt><code class="rounded-control bg-surface px-1 py-0.5">{{ $tagExample }}</code></dt>
                            <dd>{{ $tagMeaning }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.alert>
        </x-ui.form-section>

        <x-ui.form-section title="Tempo de disparo" description="Quando a mensagem sai em relação ao serviço.">
            <x-ui.field name="lead_kilometers" label="Quilômetros até a próxima revisão" optional hint="Para o gatilho de revisão programada. Ex.: 1000 km antes.">
                <x-ui.input type="number" min="0" max="50000" value="{{ old('lead_kilometers', $template?->lead_kilometers) }}" />
            </x-ui.field>

            <x-ui.field name="min_days_since_service" label="Dias após o serviço" optional hint="Para o gatilho de retorno após serviço. Padrão: 60 dias.">
                <x-ui.input type="number" min="1" max="3650" value="{{ old('min_days_since_service', $template?->min_days_since_service) }}" />
            </x-ui.field>
        </x-ui.form-section>

        <x-ui.form-section title="Status">
            <x-ui.switch name="is_active" label="Modelo ativo" :checked="(bool) ($template?->is_active ?? true)"
                         description="Ativo: entra na rodada diária de mensagens. Inativo: não dispara e não conta no limite do plano." />
        </x-ui.form-section>

        <div class="flex gap-3">
            <x-ui.button type="submit">{{ $isEdit ? 'Salvar alterações' : 'Criar modelo' }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workshop.message-templates.index')">Cancelar</x-ui.button>
        </div>
    </div>
</form>
