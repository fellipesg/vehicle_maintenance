<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Manutenções - {{ $vehicle->brand }} {{ $vehicle->model }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        .document-table {
            width: 100%;
            border-collapse: collapse;
        }

        .document-table thead {
            display: table-header-group;
        }

        .document-table > tbody > tr > td {
            vertical-align: top;
            padding: 14px 18px 18px;
        }

        .document-table > thead > tr > td {
            padding: 18px 18px 0;
        }

        .cover-document {
            page-break-after: always;
        }

        .cover-document > tbody > tr > td {
            padding: 0;
        }

        .cover-body {
            padding: 14px 18px 18px;
        }

        .os-document + .os-document {
            page-break-before: always;
        }

        .os-document thead {
            display: table-header-group;
        }

        .os-document > tbody > tr > td {
            padding: 0 18px 14px;
        }

        .os-document > thead > tr > td {
            padding: 18px 18px 0;
        }

        .letterhead-name {
            font-size: 14pt;
            font-weight: bold;
            color: #0B1C2C;
            margin-bottom: 6px;
        }

        .letterhead-line {
            font-size: 9pt;
            color: #374151;
            margin-bottom: 3px;
            line-height: 1.35;
        }

        .brand-header-bar {
            background-color: #0B1C2C;
            padding: 14px 18px;
            text-align: center;
        }

        .brand-header-bar img {
            max-height: 64px;
            max-width: 320px;
            width: auto;
            height: auto;
        }

        .letterhead {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d1d5db;
        }

        .letterhead-logo-cell {
            text-align: right;
            vertical-align: middle;
        }

        .letterhead-logo {
            display: block;
            width: {{ $workshopLogoWidth ?? 120 }}px;
            height: {{ $workshopLogoHeight ?? 130 }}px;
        }

        .title-band {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .title-band--cover {
            margin-top: 0;
        }

        .title-band td {
            background: #0B1C2C;
            color: #fff;
            font-size: 10pt;
            line-height: 14px;
            padding: 8px 12px;
            vertical-align: middle;
            border: 0;
        }

        .title-band .band-left {
            font-weight: bold;
        }

        .title-band .band-right {
            text-align: right;
            white-space: nowrap;
        }

        .vehicle-section-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0B1C2C;
            margin-bottom: 12px;
        }

        .vehicle-cover-photo {
            display: block;
            width: 100%;
            max-width: 100%;
            height: auto;
            border: 1px solid #d1d5db;
            margin-bottom: 12px;
        }

        .info-table-wrapper {
            border: 1px solid #d1d5db;
            padding: 12px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px 8px 0;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 9pt;
        }

        .info-value {
            color: #333;
            font-size: 10pt;
        }

        /* Procedência — resumo (capa) */
        .prov-summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 14px -10px 0;
        }

        .prov-count {
            width: 50%;
            padding: 10px 12px;
            border-radius: 6px;
            vertical-align: top;
        }

        .prov-count--verified {
            background-color: #f0fdfa;
            border: 1px solid #99f6e4;
            border-left: 4px solid #0f766e;
        }

        .prov-count--declared {
            background-color: #fffbeb;
            border: 1px dashed #d97706;
        }

        .prov-count-num {
            font-size: 18pt;
            font-weight: bold;
            line-height: 1;
        }

        .prov-count--verified .prov-count-num { color: #0f766e; }
        .prov-count--declared .prov-count-num { color: #92400e; }

        .prov-count-label {
            font-size: 8pt;
            color: #4b5563;
            margin-top: 4px;
        }

        .prov-dots {
            border-collapse: collapse;
            margin-top: 12px;
        }

        .prov-dots td {
            text-align: center;
            padding: 0 4px;
            vertical-align: top;
        }

        .prov-dot {
            width: 11px;
            height: 11px;
            border-radius: 6px;
            margin: 0 auto;
        }

        .prov-dot--verified {
            background-color: #0f766e;
        }

        .prov-dot--declared {
            width: 7px;
            height: 7px;
            background-color: #fffbeb;
            border: 2px dashed #92400e;
        }

        .prov-dot-date {
            font-size: 6.5pt;
            color: #6b7280;
            margin-top: 3px;
            white-space: nowrap;
        }

        .prov-dots-caption {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 6px;
        }

        /* Procedência — selo por OS */
        .seal {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .seal td {
            vertical-align: middle;
            padding: 8px 10px;
        }

        .seal--verified td {
            background-color: #f0fdfa;
            border-top: 1px solid #99f6e4;
            border-bottom: 1px solid #99f6e4;
        }

        .seal--verified td.seal-first { border-left: 4px solid #0f766e; }
        .seal--verified td.seal-last { border-right: 1px solid #99f6e4; }

        .seal--declared td {
            background-color: #fffbeb;
            border-top: 1px dashed #d97706;
            border-bottom: 1px dashed #d97706;
        }

        .seal--declared td.seal-first { border-left: 1px dashed #d97706; }
        .seal--declared td.seal-last { border-right: 1px dashed #d97706; }

        .seal-kicker {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .seal--verified .seal-kicker { color: #0f766e; }
        .seal--declared .seal-kicker { color: #92400e; }

        .seal-name {
            font-size: 10pt;
            font-weight: bold;
            color: #111827;
            margin-top: 2px;
        }

        .seal-meta {
            font-size: 7.5pt;
            color: #4b5563;
            margin-top: 2px;
            line-height: 1.35;
        }

        .seal-code {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 10pt;
            font-weight: bold;
            color: #0f766e;
            letter-spacing: 1px;
        }

        .seal-verify {
            font-size: 7pt;
            color: #6b7280;
            margin-top: 2px;
        }

        .seal-evidence {
            font-size: 8pt;
            font-weight: bold;
            color: #92400e;
        }

        .os-body-card {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d1d5db;
        }

        .os-body-card td {
            padding: 10px 12px 12px;
            vertical-align: top;
        }

        .meta-block {
            margin-bottom: 0;
        }

        .meta-line {
            margin-bottom: 5px;
            line-height: 1.4;
            padding: 2px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 9pt;
            border: 1px solid #d1d5db;
            page-break-inside: auto;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table th {
            background-color: #e5e7eb;
            padding: 8px;
            text-align: left;
            border: 1px solid #d1d5db;
            font-weight: bold;
        }

        .items-table td {
            padding: 8px;
            border: 1px solid #d1d5db;
        }

        .items-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .items-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }

        .warranty-small {
            color: #166534;
            font-size: 8pt;
        }

        .invoices-section {
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
        }

        .invoices-section h4 {
            font-size: 11pt;
            margin-bottom: 8px;
            color: #0B1C2C;
        }

        .invoice-item {
            padding: 8px 12px;
            background-color: #f9fafb;
            margin-bottom: 8px;
            border: 1px solid #d1d5db;
            font-size: 9pt;
        }

        .invoice-item strong {
            color: #0B1C2C;
        }

        .section-heading {
            font-size: 11pt;
            margin: 0 0 8px;
            color: #0B1C2C;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 40px 18px;
            color: #666;
        }

        .doc-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #d1d5db;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
    </style>
</head>
<body>
    {{-- Página 1: capa RevisaLog + dados do veículo --}}
    <table class="document-table cover-document" cellpadding="0" cellspacing="0">
        <tbody>
            <tr>
                <td>
                    @if(! empty($revisalogCoverLogoSrc))
                        <div class="brand-header-bar">
                            <img src="{{ $revisalogCoverLogoSrc }}" alt="RevisaLog">
                        </div>
                    @endif
                    <table class="title-band title-band--cover" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="band-left" width="70%" valign="middle">Histórico de manutenções</td>
                            <td class="band-right" width="30%" valign="middle">{{ now()->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>

                    <div class="cover-body">
                        <div class="vehicle-section-title">Informações do veículo</div>

                        @if(! empty($coverImageSrc))
                            <img
                                class="vehicle-cover-photo"
                                src="{{ $coverImageSrc }}"
                                alt="Foto de capa do {{ $vehicle->brand }} {{ $vehicle->model }}"
                            >
                        @endif

                        <div class="info-table-wrapper">
                            <table class="info-table" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <span class="info-label">Marca/Modelo:</span>
                                        <span class="info-value">{{ $vehicle->brand }} {{ $vehicle->model }}</span>
                                    </td>
                                    <td>
                                        <span class="info-label">Ano:</span>
                                        <span class="info-value">{{ $vehicle->year }}</span>
                                    </td>
                                </tr>
                                @if($vehicle->chassis)
                                <tr>
                                    <td colspan="2">
                                        <span class="info-label">Chassi:</span>
                                        <span class="info-value" style="font-family: DejaVu Sans Mono, monospace; font-size: 13px; letter-spacing: 0.05em;">{{ $vehicle->chassis }}</span>
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td>
                                        <span class="info-label">Placa atual:</span>
                                        <span class="info-value">{{ $vehicle->license_plate }}</span>
                                    </td>
                                    <td>
                                        <span class="info-label">RENAVAM:</span>
                                        <span class="info-value">{{ $vehicle->renavam }}</span>
                                    </td>
                                </tr>
                                @if($vehicle->current_kilometers)
                                <tr>
                                    <td colspan="2">
                                        <span class="info-label">Quilometragem:</span>
                                        <span class="info-value">{{ number_format($vehicle->current_kilometers, 0, ',', '.') }} km</span>
                                    </td>
                                </tr>
                                @endif
                                @if($vehicle->color)
                                <tr>
                                    <td colspan="2">
                                        <span class="info-label">Cor:</span>
                                        <span class="info-value">{{ $vehicle->color }}</span>
                                    </td>
                                </tr>
                                @endif
                                @if($vehicle->relationLoaded('plates') && $vehicle->plates->count() > 1)
                                <tr>
                                    <td colspan="2">
                                        <span class="info-label">Histórico de placas</span>
                                        <table cellpadding="4" cellspacing="0" width="100%" style="margin-top:6px;font-size:10px;border-collapse:collapse;">
                                            <tr style="background:#f3f4f6;">
                                                <th align="left">Placa</th>
                                                <th align="left">De</th>
                                                <th align="left">Até</th>
                                                <th align="left">Origem</th>
                                            </tr>
                                            @foreach($vehicle->plates as $plateRow)
                                            <tr>
                                                <td>{{ $plateRow->plate }}</td>
                                                <td>{{ $plateRow->started_at?->format('d/m/Y') ?? '—' }}</td>
                                                <td>{{ $plateRow->ended_at?->format('d/m/Y') ?? 'Vigente' }}</td>
                                                <td>{{ \App\Models\VehiclePlate::sourceLabel($plateRow->source) }}</td>
                                            </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                                @endif
                                @if($vehicle->motorization || $vehicle->engine)
                                <tr>
                                    @if($vehicle->motorization)
                                    <td>
                                        <span class="info-label">Motorização:</span>
                                        <span class="info-value">{{ $vehicle->motorization }}</span>
                                    </td>
                                    @else
                                    <td></td>
                                    @endif
                                    @if($vehicle->engine)
                                    <td>
                                        <span class="info-label">Código do motor:</span>
                                        <span class="info-value">{{ $vehicle->engine }}</span>
                                    </td>
                                    @else
                                    <td></td>
                                    @endif
                                </tr>
                                @endif
                            </table>
                        </div>

                        @php
                            $provenanceStrip = \App\Support\VehicleProvenanceStrip::segmentsForVehicle($vehicle);
                            $verifiedMaintenanceCount = $vehicle->maintenances->filter(fn ($m) => $m->isVerified())->count();
                            $totalMaintenanceCount = $vehicle->maintenances->count();
                        @endphp
                        @if($totalMaintenanceCount > 0)
                            @php
                                $declaredMaintenanceCount = $totalMaintenanceCount - $verifiedMaintenanceCount;
                                $provenanceDotRows = array_chunk($provenanceStrip, 12);
                            @endphp
                            <table class="prov-summary" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="prov-count prov-count--verified">
                                        <div class="prov-count-num">{{ $verifiedMaintenanceCount }}</div>
                                        <div class="prov-count-label">Com selo de oficina</div>
                                    </td>
                                    <td class="prov-count prov-count--declared">
                                        <div class="prov-count-num">{{ $declaredMaintenanceCount }}</div>
                                        <div class="prov-count-label">{{ $declaredMaintenanceCount === 1 ? 'Declarada, sem selo de oficina' : 'Declaradas, sem selo de oficina' }}</div>
                                    </td>
                                </tr>
                            </table>
                            @foreach($provenanceDotRows as $dotRow)
                                <table class="prov-dots" cellpadding="0" cellspacing="0">
                                    <tr>
                                        @foreach($dotRow as $segment)
                                            <td>
                                                <div class="prov-dot {{ $segment['is_verified'] ? 'prov-dot--verified' : 'prov-dot--declared' }}"></div>
                                                <div class="prov-dot-date">{{ $segment['date'] ? \Carbon\Carbon::parse($segment['date'])->format('m/y') : '—' }}</div>
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            @endforeach
                            <p class="prov-dots-caption">
                                Linha do tempo das {{ $totalMaintenanceCount }} manutenções, da mais antiga à mais recente:
                                <span style="color:#0f766e;">●</span> manutenções com selo de oficina ·
                                <span style="color:#92400e;">◌</span> declaradas. Detalhes nas páginas seguintes.
                            </p>
                        @endif
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    @if($vehicle->maintenances->count() > 0)
        @foreach($vehicle->maintenances as $maintenance)
            @php
                $workshopName = $maintenance->displayWorkshopName();
                $maintenanceTitle = $maintenance->maintenance_type ?: 'Manutenção';
                $maintenanceDate = \Carbon\Carbon::parse($maintenance->maintenance_date)->format('d/m/Y');
                $workshopLogo = $workshopLogos[$maintenance->id] ?? null;
            @endphp

            <table class="document-table os-document" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <td>
                            <table class="letterhead" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="75%" valign="top" style="padding:8px 10px;">
                                        @if($maintenance->workshop)
                                            <div class="letterhead-name">{{ $maintenance->workshop->name }}</div>
                                            <div class="letterhead-line">{{ $maintenance->workshop->full_address }}</div>
                                            @if($maintenance->workshop->phone)
                                                <div class="letterhead-line">Telefone: {{ $maintenance->workshop->phone }}</div>
                                            @endif
                                            @if($maintenance->workshop->whatsapp)
                                                <div class="letterhead-line">WhatsApp: {{ $maintenance->workshop->whatsapp }}</div>
                                            @endif
                                            @if($maintenance->workshop->email)
                                                <div class="letterhead-line">E-mail: {{ $maintenance->workshop->email }}</div>
                                            @endif
                                            @if($maintenance->workshop->instagram)
                                                <div class="letterhead-line">Instagram: {{ $maintenance->workshop->instagram }}</div>
                                            @endif
                                            @if($maintenance->workshop->facebook)
                                                <div class="letterhead-line">Facebook: {{ $maintenance->workshop->facebook }}</div>
                                            @endif
                                        @elseif($workshopName)
                                            <div class="letterhead-name">{{ $workshopName }}</div>
                                        @else
                                            <div class="letterhead-name">Manutenção registrada pelo proprietário</div>
                                        @endif
                                    </td>
                                    <td width="25%" class="letterhead-logo-cell" style="padding:8px 10px;">
                                        @if(! empty($workshopLogo))
                                            <img
                                                src="{{ $workshopLogo }}"
                                                alt=""
                                                class="letterhead-logo"
                                                width="{{ $workshopLogoWidth ?? 120 }}"
                                                height="{{ $workshopLogoHeight ?? 130 }}"
                                            >
                                        @elseif(! $maintenance->workshop && empty($workshopName) && ! empty($revisalogLogoSrc))
                                            <img
                                                src="{{ $revisalogLogoSrc }}"
                                                alt="RevisaLog"
                                                class="letterhead-logo"
                                                width="{{ $workshopLogoWidth ?? 120 }}"
                                                height="{{ $workshopLogoHeight ?? 130 }}"
                                            >
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <table class="title-band" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="band-left" width="70%" valign="middle">{{ $maintenanceTitle }}</td>
                                    <td class="band-right" width="30%" valign="middle">{{ $maintenanceDate }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            @php
                                $sealWorkshopName = $maintenance->verifiedWorkshop?->name ?? $workshopName;
                                $sealInvoiceCount = $maintenance->invoices->count();
                                $sealActor = $maintenance->registered_by_type === 'garage' ? 'lojista' : 'proprietário';
                            @endphp
                            @if($maintenance->isVerified())
                                <table class="seal seal--verified" cellpadding="0" cellspacing="0">
                                    <tr>
                                        @if(! empty($workshopLogo))
                                            <td class="seal-first" width="44">
                                                <img src="{{ $workshopLogo }}" width="32" height="48" alt="">
                                            </td>
                                            <td>
                                        @else
                                            <td class="seal-first">
                                        @endif
                                            <div class="seal-kicker">Selo da oficina</div>
                                            <div class="seal-name">{{ $sealWorkshopName }}</div>
                                            <div class="seal-meta">
                                                Registro feito pela própria oficina em {{ $maintenance->verified_at?->format('d/m/Y') }}
                                                · não pode ser alterado pelo proprietário
                                            </div>
                                        </td>
                                        <td class="seal-last" width="175" align="right">
                                            <div class="seal-code">{{ $maintenance->verification_code }}</div>
                                            <div class="seal-verify">revisalog.com.br/v/{{ $maintenance->verification_code }}</div>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <table class="seal seal--declared" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td class="seal-first">
                                            <div class="seal-kicker">{{ $maintenance->provenance_label }}</div>
                                            <div class="seal-meta">
                                                Registro feito pelo {{ $sealActor }} do veículo · não verificado por oficina cadastrada
                                            </div>
                                        </td>
                                        <td class="seal-last" width="175" align="right">
                                            <div class="seal-evidence">
                                                @if($sealInvoiceCount > 0)
                                                    NF anexada ({{ $sealInvoiceCount }})
                                                @else
                                                    Sem nota fiscal
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            <table class="os-body-card" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div class="meta-block">
                                            @if($maintenance->kilometers)
                                                <div class="meta-line">
                                                    <span class="info-label">Quilometragem:</span>
                                                    <span class="info-value">{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</span>
                                                </div>
                                            @endif
                                            @if($maintenance->service_category)
                                                <div class="meta-line">
                                                    <span class="info-label">Categoria:</span>
                                                    <span class="info-value">
                                                        @if($maintenance->service_category === 'mechanical') Mecânica
                                                        @elseif($maintenance->service_category === 'electrical') Elétrica
                                                        @elseif($maintenance->service_category === 'suspension') Suspensão
                                                        @elseif($maintenance->service_category === 'painting') Pintura
                                                        @elseif($maintenance->service_category === 'finishing') Acabamento
                                                        @elseif($maintenance->service_category === 'interior') Interior
                                                        @else Outra
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif
                                            @if($maintenance->generalWarranty)
                                                <div class="meta-line">
                                                    <span class="info-label">Garantia geral:</span>
                                                    <span class="info-value">{{ $maintenance->generalWarranty->name }} — até {{ $maintenance->generalWarranty->ends_at->format('d/m/Y') }}</span>
                                                </div>
                                            @endif
                                            @if($maintenance->is_manufacturer_required)
                                                <div class="meta-line">
                                                    <span class="info-label">Tipo:</span>
                                                    <span class="info-value">Exigida pelo fabricante</span>
                                                </div>
                                            @endif
                                            @if($maintenance->description)
                                                <div class="meta-line" style="margin-top: 8px;">
                                                    <span class="info-label">Descrição:</span>
                                                    <div class="info-value" style="margin-top: 4px;">{{ $maintenance->description }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @if($maintenance->items && $maintenance->items->count() > 0)
                        <tr>
                            <td>
                                <div class="section-heading">Itens da manutenção</div>
                                <table class="items-table" cellpadding="0" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Quantidade</th>
                                            <th>Preço unit.</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($maintenance->items as $item)
                                            <tr>
                                                <td>
                                                    <strong>{{ $item->name }}</strong>
                                                    @if($item->description)
                                                        <br><span style="color: #666; font-size: 8pt;">{{ $item->description }}</span>
                                                    @endif
                                                    @if($item->part_number)
                                                        <br><span style="color: #999; font-size: 8pt;">Código: {{ $item->part_number }}</span>
                                                    @endif
                                                    @if($item->warranty)
                                                        <br><span class="warranty-small">{{ $item->warranty->name }} — até {{ $item->warranty->ends_at->format('d/m/Y') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $item->quantity }}x</td>
                                                <td>R$ {{ number_format($item->unit_price ?? 0, 2, ',', '.') }}</td>
                                                <td><strong>R$ {{ number_format($item->total_price ?? 0, 2, ',', '.') }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                    @if($maintenance->invoices && $maintenance->invoices->count() > 0)
                        <tr>
                            <td>
                                <div class="invoices-section">
                                    <h4>Notas fiscais</h4>
                                    @foreach($maintenance->invoices as $invoice)
                                        <div class="invoice-item">
                                            <strong>{{ $invoice->file_name }}</strong><br>
                                            <span>DANFE nas páginas seguintes deste PDF e em anexo no e-mail.</span><br>
                                            @if($invoice->invoice_number)
                                                <span>Número: {{ $invoice->invoice_number }}</span><br>
                                            @endif
                                            @if($invoice->invoice_date)
                                                <span>Data: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</span><br>
                                            @endif
                                            @if($invoice->total_amount)
                                                <span>Valor: R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endforeach
    @else
        <div class="empty-state">
            <p>Nenhuma manutenção registrada para este veículo.</p>
        </div>
    @endif

    <div class="doc-footer">
        <p>Valide qualquer selo em revisalog.com.br/v/{código}</p>
        <p>Relatório gerado automaticamente pela Revisalog (revisalog.com.br)</p>
    </div>
</body>
</html>
