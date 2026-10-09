{{--
    Corpo do formulário de OS da oficina (Nova OS e Editar OS), depois da etapa 1 (Veículo), que a
    página desenha fora do <form>: etapas 2 Serviço, 3 Notas fiscais, 4 Peças e serviços,
    5 Garantia e 6 Fotos, cada uma um card com <fieldset> e título numerado.

    A NF-e vem antes das peças porque, com a lista de peças vazia, os itens do XML entram na OS.
    A garantia aparece sempre: sem modelo ativo, o estado vazio leva a "Criar modelo" (em nova aba,
    para não perder o formulário). A data "Válida até" acompanha a data do serviço
    (resources/js/workshop-maintenance-form.js).

    Variáveis: $maintenance (na edição), $vehicle, $orderTemplates, $itemTemplates,
    $hasActiveTemplates, $existingPhotos e $editable (edição).
--}}
@php
    use App\Enums\ServiceCategory;
    use App\Support\AppStorage;

    $maintenance = $maintenance ?? null;
    $orderTemplates = $orderTemplates ?? collect();
    $itemTemplates = $itemTemplates ?? collect();
    $hasActiveTemplates = $hasActiveTemplates ?? ($orderTemplates->where('is_active', true)->isNotEmpty() || $itemTemplates->isNotEmpty());
    $maintenanceDate = old('maintenance_date', $maintenance?->maintenance_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $issuedGeneralWarranty = $maintenance?->generalWarranty;
    $selectedGeneralTemplateId = old('general_warranty_template_id', $issuedGeneralWarranty?->warranty_template_id);
    $selectedGeneralTemplate = $orderTemplates->firstWhere('id', (int) $selectedGeneralTemplateId);
    $generalUntil = null;

    if ($selectedGeneralTemplate && filled($maintenanceDate)) {
        try {
            $generalUntil = \Carbon\CarbonImmutable::parse($maintenanceDate)->startOfDay()->addDays((int) $selectedGeneralTemplate->duration_days)->format('d/m/Y');
        } catch (\Throwable) {
            $generalUntil = null;
        }
    }

    $currentKilometers = $vehicle?->current_kilometers;
    $kilometersHint = $currentKilometers !== null
        ? 'Última quilometragem registrada do veículo: '.number_format((int) $currentKilometers, 0, ',', '.').' km. Informe o hodômetro na data do serviço.'
        : 'Informe o hodômetro na data do serviço.';
    $invoiceType = function ($invoice): string {
        $extension = strtolower(pathinfo((string) $invoice->file_name, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml' => 'XML',
            'pdf' => 'DANFE (PDF)',
            default => $extension !== '' ? strtoupper($extension) : 'Arquivo',
        };
    };
@endphp

<div class="space-y-6" data-workshop-maintenance-form>
    <x-ui.form-section id="secao-servico" :number="2" title="Serviço" description="O que foi feito, em que data e com que quilometragem.">
        <x-ui.field name="maintenance_type" label="Serviço realizado" hint="Ex.: Revisão dos 10.000 km, troca das pastilhas de freio." required>
            <x-ui.input :value="$maintenance?->maintenance_type" required maxlength="100" autocomplete="off" />
        </x-ui.field>

        <x-ui.field name="service_category" label="Categoria" required>
            <x-ui.select :options="ServiceCategory::options()" :value="$maintenance?->service_category" placeholder="Selecione a categoria" required />
        </x-ui.field>

        <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
            <x-ui.field name="maintenance_date" label="Data do serviço" required>
                <x-ui.input type="date" :value="$maintenanceDate" required data-maintenance-date />
            </x-ui.field>
            <x-ui.field name="kilometers" label="Quilometragem (km)" :hint="$kilometersHint" required>
                <x-ui.input-group>
                    <x-ui.input type="number" :value="$maintenance?->kilometers ?? $currentKilometers" min="0" max="9999999" step="1" inputmode="numeric" required class="tabular-nums" />
                    <x-slot:trailing>km</x-slot:trailing>
                </x-ui.input-group>
            </x-ui.field>
        </div>

        <x-ui.field name="description" label="Descrição" optional hint="O que o cliente precisa saber: diagnóstico, recomendações, próxima revisão. Não inclua nome, CPF, telefone ou placa do cliente.">
            <x-ui.textarea :value="$maintenance?->description" rows="4" autosize />
        </x-ui.field>

        <x-ui.checkbox name="is_manufacturer_required" label="Revisão obrigatória do fabricante"
                       description="Do plano de revisões do manual, que mantém a garantia de fábrica."
                       :checked="(bool) ($maintenance?->is_manufacturer_required ?? false)" />
    </x-ui.form-section>

    <x-ui.form-section id="secao-notas" :number="3" title="Notas fiscais" description="Anexe a NF-e antes de listar as peças: com a lista de peças vazia, os itens do XML entram sozinhos na OS.">
        @if($maintenance && $maintenance->relationLoaded('invoices') && $maintenance->invoices->isNotEmpty())
            <div class="space-y-2">
                <p class="text-sm font-medium text-foreground">Já anexadas</p>
                <ul role="list" class="divide-y divide-border rounded-control border border-border">
                    @foreach($maintenance->invoices as $invoice)
                        <li class="flex min-h-12 items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-ui.icon name="document-text" class="size-5 text-muted-foreground" />
                                <span class="truncate text-foreground">{{ $invoice->file_name }}</span>
                                <x-ui.badge size="sm">{{ $invoiceType($invoice) }}</x-ui.badge>
                            </span>
                            <a href="{{ AppStorage::url($invoice->file_path) }}" target="_blank" rel="noopener" class="link shrink-0">Abrir<span class="sr-only"> {{ $invoice->file_name }} (abre em nova aba)</span></a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.field name="invoices[]" :label="$maintenance ? 'Anexar mais notas fiscais' : 'Arquivos da nota fiscal'" optional
                    hint="O XML da NF-e importa as peças com mais precisão; o PDF (DANFE) vale como comprovante.">
            <x-ui.file-input accept="application/pdf,.pdf,application/xml,.xml,text/xml" multiple :max-mb="10" />
        </x-ui.field>
    </x-ui.form-section>

    @include('partials.maintenance-items-form', [
        'items' => $maintenance?->items ?? collect(),
        'itemTemplates' => $itemTemplates,
        'maintenanceDate' => $maintenanceDate,
        'sectionNumber' => 4,
    ])

    <x-ui.form-section id="secao-garantia" :number="5" title="Garantia" description="A garantia vale a partir da data do serviço e aparece para o cliente e no PDF do histórico." data-warranty-section>
        @if($orderTemplates->isNotEmpty())
            @if($issuedGeneralWarranty)
                <p class="text-sm text-foreground">Garantia emitida: {{ $issuedGeneralWarranty->name }} · válida até {{ $issuedGeneralWarranty->ends_at->format('d/m/Y') }}</p>
            @endif
            <x-ui.field name="general_warranty_template_id" label="Garantia geral da OS" optional>
                <x-ui.select data-warranty-select>
                    <option value="">Sem garantia geral</option>
                    @foreach($orderTemplates as $template)
                        <option value="{{ $template->id }}" @selected((string) $selectedGeneralTemplateId === (string) $template->id) data-duration-days="{{ (int) $template->duration_days }}">{{ $template->name }} ({{ $template->duration_days }} dias)@unless($template->is_active) (modelo desativado, mantido nesta OS)@endunless</option>
                    @endforeach
                </x-ui.select>
                @if($issuedGeneralWarranty)
                    <x-slot:hint>Trocar o modelo ou escolher "Sem garantia geral" encerra a garantia emitida quando você salvar.</x-slot:hint>
                @endif
            </x-ui.field>
            <p id="general_warranty_template_id-until" class="text-sm text-muted-foreground empty:hidden" data-warranty-until aria-live="polite">@if($generalUntil)Válida até <span class="font-medium text-foreground tabular-nums">{{ $generalUntil }}</span>@endif</p>
            @if($itemTemplates->isNotEmpty())
                <p class="text-sm text-muted-foreground">Para a garantia de uma peça, escolha o modelo na linha do item, em "Peças e serviços".</p>
            @endif
        @elseif(! $hasActiveTemplates)
            <x-ui.empty-state size="sm" icon="shield-check" heading-level="h3"
                              title="Você ainda não tem modelos de garantia ativos"
                              description="Crie um modelo com o termo e o prazo. Ele aparece aqui e em cada peça, com a data de validade calculada."
                              data-warranty-empty>
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="plus" :href="route('workshop.warranty-templates.create')" target="_blank" rel="noopener">Criar modelo<span class="sr-only"> de garantia (abre em nova aba)</span></x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <p class="text-sm text-muted-foreground">
                Nenhum modelo de garantia geral ativo. As peças podem ter garantia própria em "Peças e serviços".
                <a href="{{ route('workshop.warranty-templates.create') }}" target="_blank" rel="noopener" class="link underline">Criar modelo<span class="sr-only"> de garantia (abre em nova aba)</span></a>
            </p>
        @endif
    </x-ui.form-section>

    @include('partials.maintenance-photos-form', [
        'existingPhotos' => $existingPhotos ?? collect(),
        'editable' => $editable ?? false,
        'sectionNumber' => 6,
    ])
</div>
