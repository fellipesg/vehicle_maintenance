{{--
    "Validações": manutenções que clientes declararam citando a oficina (workshop_review_status =
    pending). Confirmar aplica o Selo da oficina e passa a OS para a oficina; "Não reconheço" tira o
    vínculo (com motivo opcional, num diálogo por serviço). Os dois avisam o cliente.
--}}
@extends('layouts.app')

@section('title', 'Validações')

@php
    $money = fn ($value): string => 'R$ '.number_format((float) $value, 2, ',', '.');
@endphp

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header title="Validações"
                          description="Serviços que clientes registraram citando a sua oficina. Confirme os que foram feitos aí para emitir o Selo da oficina." />

        <div class="space-y-6">
            <x-ui.alert variant="info" title="Por que validar" data-reviews-why>
                O serviço confirmado ganha o Selo da oficina com a sua logo, entra nos indicadores e o cliente passa a receber as mensagens que você configurar em Mensagens. Depois de confirmar, a OS é sua: só a oficina corrige quilometragem, itens e anexos.
            </x-ui.alert>

            @if($recentConfirmed > 0 || $recentRejected > 0)
                <p class="text-sm text-muted-foreground" data-reviews-recent>
                    Nos últimos {{ \App\Http\Controllers\Web\Workshop\ReviewController::RECENT_DAYS }} dias:
                    {{ $recentConfirmed === 1 ? '1 confirmado' : $recentConfirmed.' confirmados' }} e
                    {{ $recentRejected === 1 ? '1 não reconhecido' : $recentRejected.' não reconhecidos' }}.
                </p>
            @endif

            @if($pending->isEmpty())
                <x-ui.empty-state icon="check-circle" heading-level="h2" title="Nenhum serviço aguardando validação"
                                  description="Quando um cliente registrar um serviço e escolher a sua oficina, ele aparece aqui e você recebe um aviso." data-reviews-empty />
            @else
                <ul role="list" class="space-y-3" data-reviews-list>
                    @foreach($pending as $maintenance)
                        @php
                            $vehicle = $maintenance->vehicle;
                            $vehicleName = $vehicle ? trim($vehicle->brand.' '.$vehicle->model) : 'Veículo';
                            $plate = $vehicle?->license_plate;
                            $declarantFirstName = \Illuminate\Support\Str::before(trim((string) $maintenance->user?->name), ' ');
                            $declaredBy = $maintenance->registered_by_type === 'garage' ? 'Lojista' : 'Proprietário';
                            $invoiceCount = $maintenance->invoices->count();
                            $photoCount = $maintenance->photos->count();
                            $dialogId = 'nao-reconheco-'.$maintenance->id;
                            $serviceLabel = $maintenance->maintenance_type.($plate ? ' · '.$plate : '');
                        @endphp
                        <li class="rounded-card border border-border bg-surface p-4 shadow-sm sm:p-5" data-review="{{ $maintenance->id }}">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-semibold text-foreground">{{ $maintenance->maintenance_type }}</h2>
                                        <x-ui.badge variant="declared">Declarada pelo {{ mb_strtolower($declaredBy) }}</x-ui.badge>
                                    </div>
                                    <p class="text-sm text-foreground">
                                        {{ $vehicleName }}@if($plate) · <span class="font-mono tracking-wider">{{ $plate }}</span>@endif
                                    </p>
                                    <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                        <div class="flex gap-1.5"><dt class="text-muted-foreground">Data do serviço:</dt><dd class="tabular-nums text-foreground">{{ $maintenance->maintenance_date?->format('d/m/Y') }}</dd></div>
                                        <div class="flex gap-1.5"><dt class="text-muted-foreground">Quilometragem:</dt><dd class="tabular-nums text-foreground">{{ number_format((int) $maintenance->kilometers, 0, ',', '.') }} km</dd></div>
                                        <div class="flex gap-1.5"><dt class="text-muted-foreground">Cliente:</dt><dd class="text-foreground">{{ $declarantFirstName !== '' ? $declarantFirstName : $declaredBy }}</dd></div>
                                        <div class="flex gap-1.5"><dt class="text-muted-foreground">Itens:</dt><dd class="tabular-nums text-foreground">{{ (int) $maintenance->items_count }}@if((float) $maintenance->items_total > 0) · {{ $money($maintenance->items_total) }}@endif</dd></div>
                                        <div class="flex gap-1.5"><dt class="text-muted-foreground">Anexos:</dt><dd class="text-foreground">{{ $invoiceCount === 1 ? '1 nota fiscal' : $invoiceCount.' notas fiscais' }}, {{ $photoCount === 1 ? '1 foto' : $photoCount.' fotos' }}</dd></div>
                                    </dl>
                                    @if(filled($maintenance->description))
                                        <p class="line-clamp-2 max-w-prose text-sm text-muted-foreground">{{ $maintenance->description }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                                    <x-ui.button variant="ghost" size="sm" icon="eye" :href="route('workshop.maintenances.show', $maintenance)">
                                        Ver detalhes<span class="sr-only"> de {{ $serviceLabel }}</span>
                                    </x-ui.button>
                                    <x-ui.button variant="secondary" size="sm" icon="x-mark" data-dialog-open="{{ $dialogId }}">
                                        Não reconheço<span class="sr-only"> {{ $serviceLabel }}</span>
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('workshop.reviews.confirm', $maintenance) }}"
                                          data-confirm="O serviço recebe o Selo da oficina e passa a ser da sua oficina: o cliente não edita mais e é avisado."
                                          data-confirm-title="Confirmar {{ $serviceLabel }}?"
                                          data-confirm-action-label="Confirmar serviço">
                                        @csrf
                                        <x-ui.button type="submit" size="sm" icon="shield-check" loading-label="Confirmando…">
                                            Confirmar serviço<span class="sr-only"> {{ $serviceLabel }}</span>
                                        </x-ui.button>
                                    </form>
                                </div>
                            </div>

                            <x-ui.dialog :id="$dialogId" title="Não reconhece este serviço?"
                                         :description="'O cliente é avisado e o vínculo com a oficina sai de '.$serviceLabel.'. O registro continua no histórico do veículo, como declarado.'">
                                <form method="POST" action="{{ route('workshop.reviews.reject', $maintenance) }}" id="{{ $dialogId }}-form">
                                    @csrf
                                    <x-ui.field name="note" label="Motivo" optional hint="O cliente lê este texto. Ex.: não atendemos este veículo nessa data.">
                                        <x-ui.textarea rows="3" maxlength="500" counter />
                                    </x-ui.field>
                                </form>
                                <x-slot:footer>
                                    <x-ui.button variant="secondary" data-dialog-close>Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="danger" :form="$dialogId.'-form'" loading-label="Enviando…">Não reconheço</x-ui.button>
                                </x-slot:footer>
                            </x-ui.dialog>
                        </li>
                    @endforeach
                </ul>

                @if($pending->hasPages())
                    <div class="pt-2">{{ $pending->links() }}</div>
                @endif
            @endif
        </div>
    </x-ui.container>
@endsection
