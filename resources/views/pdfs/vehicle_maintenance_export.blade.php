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

        .os-document {
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

                        @if($vehicle->maintenances->count() > 0)
                            <p style="margin-top: 16px; font-size: 9pt; color: #666;">
                                {{ $vehicle->maintenances->count() }} manutenção(ões) registrada(s) — detalhes nas páginas seguintes.
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
        <p>Relatório gerado automaticamente pela Revisalog (revisalog.com.br)</p>
    </div>
</body>
</html>
