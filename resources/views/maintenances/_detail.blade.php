{{--
    Detalhe de uma manutenção, igual para Proprietário, Lojista e Oficina (e para o Admin, se um
    dia tiver a tela). A página desenha o cabeçalho (x-ui.page-header com o único H1, trilha e
    ações) e inclui este corpo:

    @include('maintenances._detail', ['maintenance' => $maintenance, 'portal' => \App\Enums\Portal::Owner])

    Cabeçalho sugerido: título = tipo do serviço; descrição = "Marca Modelo · PLACA · dd/mm/aaaa";
    trilha = Início › Manutenções (ou Estoque › veículo) › serviço.

    Variáveis:
    - $maintenance (obrigatória). As relações que faltarem são carregadas aqui.
    - $portal: App\Enums\Portal ou o valor dele ('user', 'garage', 'workshop', 'admin'); define o
      link do veículo.
    - $vehicleUrl: link do veículo (string), false para não linkar; sem ela, a ficha do portal.

    Seções: procedência (Selo da oficina ou Declarada), detalhes do serviço, garantia do serviço,
    peças e serviços (tabela empilhada no celular, com a garantia de cada item), notas fiscais e
    fotos (antes e depois lado a lado; cada miniatura abre a <x-ui.lightbox> na foto clicada, com
    carrossel, "Foto 2 de 6", teclado e deslizar no celular, e alt contextual
    "Foto 2 de 6 — {serviço}, {data}. {grupo}").
--}}
@php
    use App\Enums\ServiceCategory;
    use App\Models\MaintenancePhoto;
    use App\Support\AppStorage;
    use App\Support\Maintenance\MaintenanceLinks;

    $maintenance->loadMissing([
        'vehicle',
        'items.warranty',
        'invoices',
        'photos',
        'generalWarranty',
        'workshop',
        'verifiedWorkshop',
        'user',
    ]);

    // OS de oficina sem proprietário: forma mínima para quem não é a oficina autora.
    $maintenance = \App\Support\Maintenance\MaintenanceRedactor::redact($maintenance, auth()->user());

    $detailPortal = MaintenanceLinks::portal($portal ?? null);
    $detailVehicle = $maintenance->vehicle;
    $detailVehicleUrlOption = $vehicleUrl ?? null;
    $detailVehicleUrl = match (true) {
        $detailVehicleUrlOption === false => null,
        is_string($detailVehicleUrlOption) && $detailVehicleUrlOption !== '' => $detailVehicleUrlOption,
        default => $detailVehicle ? MaintenanceLinks::vehicleUrl($detailVehicle, $detailPortal) : null,
    };
    $detailVehicleName = $detailVehicle ? trim($detailVehicle->brand.' '.$detailVehicle->model) : null;
    $detailCategory = ServiceCategory::labelFor($maintenance->service_category);
    $detailWorkshop = $maintenance->displayWorkshopName();
    $detailWarranty = $maintenance->generalWarranty;
    $detailItems = $maintenance->items;
    $detailItemsWithWarranty = $detailItems->filter(fn ($item): bool => $item->warranty !== null)->count();
    $detailLineTotal = fn ($item): ?float => $item->total_price !== null
        ? (float) $item->total_price
        : ($item->unit_price !== null ? (float) $item->unit_price * (float) $item->quantity : null);
    $detailItemsTotal = $detailItems->sum(fn ($item): float => $detailLineTotal($item) ?? 0.0);
    $detailMoney = fn (?float $value): string => $value === null ? '—' : 'R$ '.number_format($value, 2, ',', '.');
    $detailPhotoGroups = [
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_BEFORE => 'Carro antes do serviço',
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_DURING => 'Carro durante o serviço',
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_AFTER => 'Carro depois do serviço',
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_BEFORE => 'Peças ao retirar',
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_DURING => 'Peças durante o serviço',
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_AFTER => 'Peças novas ou depois do serviço',
    ];
    $detailPhotos = $maintenance->photos->groupBy(fn ($photo): string => $photo->subject.'_'.$photo->stage);
    // Antes e depois do mesmo assunto ficam lado a lado a partir de md; "durante" ocupa a linha toda.
    $detailPhotoWideGroups = [
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_DURING,
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_DURING,
    ];
    $detailPhotoOrder = [
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_BEFORE,
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_AFTER,
        MaintenancePhoto::SUBJECT_VEHICLE.'_'.MaintenancePhoto::STAGE_DURING,
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_BEFORE,
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_AFTER,
        MaintenancePhoto::SUBJECT_PART.'_'.MaintenancePhoto::STAGE_DURING,
    ];
    $detailPhotoViewerId = 'fotos-manutencao-'.$maintenance->id;
    $detailPhotoTotal = collect($detailPhotoOrder)->sum(fn (string $key): int => $detailPhotos->get($key)?->count() ?? 0);
    // Alt contextual, o mesmo na miniatura e na lightbox: "Foto 2 de 4 — Troca de óleo, 12/03/2025.
    // Carro antes do serviço". A numeração segue a ordem da tela (antes, depois, durante).
    $detailPhotoContext = collect([
        filled($maintenance->maintenance_type) ? $maintenance->maintenance_type : ($detailCategory ?: 'Serviço'),
        $maintenance->maintenance_date?->format('d/m/Y'),
    ])->filter()->implode(', ');
    $detailPhotoItems = [];
    $detailPhotoPositions = [];

    foreach ($detailPhotoOrder as $photoGroupKey) {
        foreach ($detailPhotos->get($photoGroupKey, collect()) as $photo) {
            $detailPhotoPositions[$photo->id] = count($detailPhotoItems);
            $detailPhotoItems[] = [
                'src' => $photo->url,
                'caption' => $detailPhotoGroups[$photoGroupKey],
                'alt' => 'Foto '.(count($detailPhotoItems) + 1).' de '.$detailPhotoTotal.' — '.$detailPhotoContext.'. '.$detailPhotoGroups[$photoGroupKey],
            ];
        }
    }
    $detailInvoiceType = function ($invoice): string {
        $extension = strtolower(pathinfo((string) $invoice->file_name, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml' => 'XML',
            'pdf' => 'DANFE (PDF)',
            default => $extension !== '' ? strtoupper($extension) : 'Arquivo',
        };
    };
@endphp

<div class="space-y-6" data-slot="maintenance-detail" data-maintenance-id="{{ $maintenance->id }}">
    <x-provenance-seal :maintenance="$maintenance" class="bg-surface" />

    <x-ui.card as="section" title="Detalhes do serviço" heading-level="h2">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm lg:grid-cols-3">
            @if ($detailVehicle)
                <div class="col-span-2 sm:col-span-1">
                    <dt class="text-muted-foreground">Veículo</dt>
                    <dd class="mt-0.5 flex flex-wrap items-center gap-x-2 font-medium text-foreground">
                        @if ($detailVehicleUrl)
                            <a href="{{ $detailVehicleUrl }}" class="link">{{ $detailVehicleName }}</a>
                        @else
                            <span>{{ $detailVehicleName }}</span>
                        @endif
                        @if (filled($detailVehicle->license_plate))
                            <span class="rounded-md border border-border-strong px-1.5 py-px font-mono text-xs tracking-wider"><span class="sr-only">Placa </span>{{ $detailVehicle->license_plate }}</span>
                        @endif
                    </dd>
                </div>
            @endif
            <div>
                <dt class="text-muted-foreground">Data do serviço</dt>
                <dd class="mt-0.5 font-medium text-foreground tabular-nums">{{ $maintenance->maintenance_date?->format('d/m/Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Quilometragem</dt>
                <dd class="mt-0.5 font-medium text-foreground tabular-nums">{{ $maintenance->kilometers !== null ? number_format((int) $maintenance->kilometers, 0, ',', '.').' km' : 'Não informada' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Categoria</dt>
                <dd class="mt-0.5">
                    @if ($detailCategory)
                        <x-ui.badge>{{ $detailCategory }}</x-ui.badge>
                    @else
                        <span class="text-foreground">Não informada</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Oficina</dt>
                <dd class="mt-0.5 font-medium text-foreground">{{ $detailWorkshop ?: 'Não informada' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Procedência</dt>
                <dd class="mt-0.5"><x-ui.badge :variant="$maintenance->isVerified() ? 'seal' : 'declared'">{{ $maintenance->provenance_label }}</x-ui.badge></dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Revisão obrigatória do fabricante</dt>
                <dd class="mt-0.5 font-medium text-foreground">{{ $maintenance->is_manufacturer_required ? 'Sim' : 'Não' }}</dd>
            </div>
        </dl>
        @if (filled($maintenance->description))
            <div class="mt-4 border-t border-border pt-4">
                <p class="text-sm text-muted-foreground">Descrição</p>
                <p class="mt-1 text-sm whitespace-pre-line text-foreground">{{ $maintenance->description }}</p>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card as="section" title="Garantia do serviço" heading-level="h2" data-slot="maintenance-warranty">
        @if ($detailWarranty)
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge :variant="$detailWarranty->isVigente() ? 'success' : 'neutral'" dot>{{ $detailWarranty->isVigente() ? 'Em garantia' : 'Garantia encerrada' }}</x-ui.badge>
                @if ($detailWarranty->ends_at)
                    <span class="text-sm text-foreground">Válida até <span class="font-medium tabular-nums">{{ $detailWarranty->ends_at->format('d/m/Y') }}</span></span>
                @endif
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                @if (filled($detailWarranty->name))
                    <div>
                        <dt class="text-muted-foreground">Termo</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $detailWarranty->name }}</dd>
                    </div>
                @endif
                @if ($detailWarranty->starts_at)
                    <div>
                        <dt class="text-muted-foreground">Início</dt>
                        <dd class="mt-0.5 font-medium text-foreground tabular-nums">{{ $detailWarranty->starts_at->format('d/m/Y') }}</dd>
                    </div>
                @endif
                @if ($detailWarranty->duration_days)
                    <div>
                        <dt class="text-muted-foreground">Prazo</dt>
                        <dd class="mt-0.5 font-medium text-foreground tabular-nums">{{ $detailWarranty->duration_days }} {{ (int) $detailWarranty->duration_days === 1 ? 'dia' : 'dias' }}</dd>
                    </div>
                @endif
            </dl>
            @if (filled($detailWarranty->body))
                <details class="group mt-4 rounded-control border border-border">
                    <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-2 px-3 text-sm font-medium text-link hover:text-link-hover [&::-webkit-details-marker]:hidden">
                        Ler o termo de garantia
                        <x-ui.icon name="chevron-down" class="size-4 transition-transform duration-fast motion-reduce:transition-none group-open:rotate-180" />
                    </summary>
                    <div class="border-t border-border px-3 py-3 text-sm whitespace-pre-line text-foreground">{{ $detailWarranty->body }}</div>
                </details>
            @endif
        @else
            <p class="text-sm text-muted-foreground">Nenhuma garantia do serviço registrada.</p>
        @endif
        @if ($detailItemsWithWarranty > 0)
            <p class="mt-3 text-sm text-muted-foreground">
                {{ $detailItemsWithWarranty === 1 ? '1 peça tem garantia própria' : $detailItemsWithWarranty.' peças têm garantia própria' }}, na tabela de peças e serviços.
            </p>
        @endif
    </x-ui.card>

    <x-ui.section title="Peças e serviços" data-slot="maintenance-items">
        <x-ui.table caption="Peças e serviços" stack empty="Nenhum item registrado" empty-icon="wrench-screwdriver">
            <x-slot:head>
                <tr>
                    <th>Item</th>
                    <th>Garantia</th>
                    <th class="text-right">Qtd.</th>
                    <th class="text-right">Preço unit.</th>
                    <th class="text-right">Total</th>
                </tr>
            </x-slot:head>
            @foreach ($detailItems as $item)
                <tr>
                    <th scope="row" class="align-top font-medium">
                        <span class="block text-foreground">{{ $item->name }}</span>
                        @if (filled($item->part_number))
                            <span class="mt-0.5 block text-xs font-normal text-muted-foreground">Código da peça: {{ $item->part_number }}</span>
                        @endif
                        @if (filled($item->description))
                            <span class="mt-0.5 block text-xs font-normal text-muted-foreground">{{ $item->description }}</span>
                        @endif
                    </th>
                    <td class="align-top">
                        @if ($item->warranty)
                            <span class="inline-flex flex-col items-end gap-1 md:items-start">
                                <x-ui.badge size="sm" :variant="$item->isUnderWarranty() ? 'success' : 'neutral'" dot>{{ $item->isUnderWarranty() ? 'Em garantia' : 'Garantia encerrada' }}</x-ui.badge>
                                <span class="text-xs text-muted-foreground tabular-nums">{{ $item->warrantyPeriodLabel() }}</span>
                            </span>
                        @else
                            <span class="text-muted-foreground">Sem garantia</span>
                        @endif
                    </td>
                    <td class="text-right align-top whitespace-nowrap">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                    <td class="text-right align-top whitespace-nowrap">{{ $detailMoney($item->unit_price !== null ? (float) $item->unit_price : null) }}</td>
                    <td class="text-right align-top font-medium whitespace-nowrap">{{ $detailMoney($detailLineTotal($item)) }}</td>
                </tr>
            @endforeach
            @if ($detailItems->isNotEmpty() && $detailItemsTotal > 0)
                <x-slot:foot>
                    <tr>
                        <th scope="row" colspan="4" class="text-right">Total dos itens</th>
                        <td class="text-right whitespace-nowrap">{{ $detailMoney($detailItemsTotal) }}</td>
                    </tr>
                </x-slot:foot>
            @endif
        </x-ui.table>
    </x-ui.section>

    @if ($maintenance->invoices->isNotEmpty())
        <x-ui.section :title="'Notas fiscais ('.$maintenance->invoices->count().')'" data-slot="maintenance-invoices">
            <ul role="list" class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface">
                @foreach ($maintenance->invoices as $invoice)
                    <li>
                        <a href="{{ AppStorage::url($invoice->file_path) }}" target="_blank" rel="noopener" class="flex min-h-12 items-center justify-between gap-3 px-4 py-3 text-sm transition-colors duration-fast ease-smooth-out hover:bg-surface-muted/60 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring motion-reduce:transition-none">
                            <span class="flex min-w-0 items-center gap-2 font-medium text-foreground">
                                <x-ui.icon name="document-text" class="size-5 text-muted-foreground" />
                                <span class="truncate">{{ $invoice->file_name }}</span>
                                <x-ui.badge size="sm">{{ $detailInvoiceType($invoice) }}</x-ui.badge>
                            </span>
                            <span class="inline-flex shrink-0 items-center gap-1 font-medium text-link">
                                Abrir
                                <x-ui.icon name="arrow-top-right-on-square" class="size-4" />
                                <span class="sr-only">{{ $invoice->file_name }} (abre em nova aba)</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.section>
    @endif

    @if ($detailPhotoTotal > 0)
        {{--
            Fotos: antes e depois lado a lado. Sem JS, cada miniatura abre a foto em nova aba; com JS
            (resources/js/ui/lightbox.js) abre a <x-ui.lightbox> na foto clicada.
        --}}
        <x-ui.section :title="'Fotos ('.$detailPhotoTotal.')'" data-slot="maintenance-photos">
            <div class="grid gap-6 md:grid-cols-2" data-photo-gallery="{{ $detailPhotoViewerId }}">
                @foreach ($detailPhotoOrder as $photoGroupKey)
                    @if ($detailPhotos->has($photoGroupKey))
                        @php
                            $photoGroupLabel = $detailPhotoGroups[$photoGroupKey];
                            $photoGroupIsWide = in_array($photoGroupKey, $detailPhotoWideGroups, true);
                        @endphp
                        <div @class(['md:col-span-2' => $photoGroupIsWide]) data-photo-group="{{ $photoGroupKey }}">
                            <h3 class="text-sm font-semibold text-foreground">{{ $photoGroupLabel }}</h3>
                            <ul role="list" @class([
                                'mt-2 grid grid-cols-2 gap-3',
                                'sm:grid-cols-4' => $photoGroupIsWide,
                                'sm:grid-cols-3 md:grid-cols-2 lg:grid-cols-3' => ! $photoGroupIsWide,
                            ])>
                                @foreach ($detailPhotos[$photoGroupKey] as $photo)
                                    @php($photoPosition = $detailPhotoPositions[$photo->id])
                                    <li>
                                        <a
                                            href="{{ $photo->url }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="block overflow-hidden rounded-control border border-border bg-surface-muted transition-colors duration-fast ease-smooth-out hover:border-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                                            data-lightbox-open="{{ $detailPhotoViewerId }}"
                                            data-lightbox-index="{{ $photoPosition }}"
                                        >
                                            <img src="{{ $photo->url }}" alt="{{ $detailPhotoItems[$photoPosition]['alt'] }}" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-contain">
                                            <span class="sr-only" data-lightbox-new-tab-hint>(abre em nova aba)</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </x-ui.section>

        <x-ui.lightbox :id="$detailPhotoViewerId" title="Fotos do serviço" :items="$detailPhotoItems" />
    @endif
</div>
