{{--
    Histórico de manutenções em PDF (Dompdf, App\Services\Vehicle\VehicleMaintenancePdfExporter).

    Paleta: textos e bordas na escala automotive do web (#344453 texto, #5a7289 rótulo, #d1dae4
    borda, #e8edf2 e #f4f7fa fundos), navy #0B1C2C nos títulos e os tokens de procedência (#0f766e
    Selo da oficina, #92400e Declarada). O timbre da OS mantém a borda #d1d5db (.ai/rules/pdfs.md).
    Datas e horas no fuso de exibição (App\Support\DisplayTime, America/Sao_Paulo), o mesmo do
    /v/{código}. Sem SVG: o Dompdf não desenha QR em SVG de forma
    confiável, então o selo leva só o código e o endereço de conferência. O rodapé com
    "Página X de Y" é desenhado pelo exporter (page_text), fora do HTML. Com $identifiersMasked (quem
    pediu o PDF não é o dono atual), chassi e RENAVAM saem parciais (VehicleIdentifierMask) e o código
    do motor não aparece.

    As vistorias cautelares chegam em $inspections, **já limitadas e redigidas** pelo exporter
    (Vehicle::inspectionsVisibleTo + InspectionRedactor): a view não decide quem vê o quê, e a
    ausência de resumo e de observação é o que diz que aquele leitor só tem a forma mínima. Elas
    ficam fora de toda conta de manutenção — da linha de pontos e do contador de procedência — porque
    a vistoria afirma algo *sobre* o carro e a manutenção registra o que foi feito *nele*
    (.ai/rules/inspections.md).
--}}
@php
    $displayTimezone = \App\Support\DisplayTime::timezone();
    $hasMaintenances = $vehicle->maintenances->count() > 0;
    // Laudos já redigidos pelo exporter; sem a variável (chamadas antigas da view) o PDF é só o
    // histórico de manutenções, como sempre foi.
    $pdfInspections = \Illuminate\Support\Collection::make($inspections ?? []);
    $hasInspections = $pdfInspections->isNotEmpty();
    $pdfIdentifiersMasked = (bool) ($identifiersMasked ?? false);
    $pdfChassis = $pdfIdentifiersMasked ? \App\Support\Vehicle\VehicleIdentifierMask::chassis($vehicle->chassis) : $vehicle->chassis;
    $pdfRenavam = $pdfIdentifiersMasked ? \App\Support\Vehicle\VehicleIdentifierMask::renavam($vehicle->renavam) : $vehicle->renavam;
    $pdfEngine = $pdfIdentifiersMasked ? null : $vehicle->engine;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de manutenções · {{ $vehicle->brand }} {{ $vehicle->model }} · RevisaLog</title>
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
            color: #344453;
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

        /* Sem manutenções, a capa é a única página: o aviso fica nela, sem folha em branco depois. */
        .cover-document--only {
            page-break-after: auto;
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
            color: #344453;
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

        .ownership-unverified {
            border: 1px solid #c2a21a;
            background: #fdf7e2;
            color: #6b5600;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 10px;
            line-height: 1.4;
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
            border: 1px solid #d1dae4;
            margin-bottom: 12px;
        }

        .info-table-wrapper {
            border: 1px solid #d1dae4;
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
            color: #5a7289;
            font-size: 9pt;
        }

        .info-value {
            color: #344453;
            font-size: 10pt;
        }

        /* Procedência — resumo (capa): contador compacto e linha de pontos (.ai/rules/theme.md) */
        .prov-heading {
            font-size: 11pt;
            font-weight: bold;
            color: #0B1C2C;
            margin-top: 16px;
        }

        .prov-counter {
            font-size: 10pt;
            color: #5a7289;
            margin-top: 4px;
        }

        .prov-counter--sealed {
            font-weight: bold;
            color: #0f766e;
        }

        .prov-counter--declared {
            font-weight: bold;
            color: #92400e;
        }

        .prov-dots {
            border-collapse: collapse;
            margin-top: 12px;
        }

        .prov-dots td {
            text-align: center;
            padding: 0 2px;
            vertical-align: top;
        }

        .prov-dot {
            width: 10px;
            height: 10px;
            border-radius: 5px;
            margin: 0 auto;
        }

        .prov-dot--verified {
            background-color: #0f766e;
        }

        .prov-dot--declared {
            width: 6px;
            height: 6px;
            background-color: #fffbeb;
            border: 2px dashed #92400e;
        }

        .prov-dot-date {
            font-size: 6.5pt;
            color: #5a7289;
            margin-top: 3px;
            white-space: nowrap;
        }

        .prov-dots-caption {
            font-size: 7.5pt;
            color: #5a7289;
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
            color: #0B1C2C;
            margin-top: 2px;
        }

        .seal-meta {
            font-size: 7.5pt;
            color: #455a6e;
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
            color: #5a7289;
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
            border: 1px solid #d1dae4;
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
            border: 1px solid #d1dae4;
            page-break-inside: auto;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table th {
            background-color: #e8edf2;
            color: #0B1C2C;
            padding: 8px;
            text-align: left;
            border: 1px solid #d1dae4;
            font-weight: bold;
        }

        .items-table td {
            padding: 8px;
            border: 1px solid #d1dae4;
        }

        .items-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .items-table tr:nth-child(even) td {
            background-color: #f4f7fa;
        }

        .item-detail {
            color: #5a7289;
            font-size: 8pt;
        }

        .plates-table {
            margin-top: 6px;
            font-size: 8pt;
            border-collapse: collapse;
        }

        .plates-table th {
            background-color: #e8edf2;
            color: #0B1C2C;
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
            background-color: #f4f7fa;
            margin-bottom: 8px;
            border: 1px solid #d1dae4;
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

        .photo-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .photo-grid tr {
            page-break-inside: avoid;
        }

        .photo-grid td {
            width: 50%;
            padding: 0 8px 0 0;
            vertical-align: top;
        }

        .photo-caption {
            margin-top: 3px;
            font-size: 8pt;
            color: #5a7289;
        }

        .photo-more {
            margin-top: 6px;
            font-size: 8pt;
            color: #5a7289;
        }

        .empty-state {
            margin-top: 16px;
            padding: 16px 12px;
            border: 1px dashed #d1dae4;
            text-align: center;
            color: #5a7289;
        }

        /* Vistoria cautelar (laudo) — documento irmão da OS e sem os tokens de procedência.
           O trilho teal #0f766e e o âmbar #92400e desta folha dizem "Selo da oficina" e "Declarada",
           que é a afirmação "eu fiz este serviço"; o laudo afirma "eu examinei e atesto o que
           encontrei", e emprestar os dois diluiria a única primitiva de confiança do produto
           (.ai/rules/inspections.md e theme.md). Quem carrega o significado aqui é o veredito, nas
           mesmas três cores semânticas do badge da web (x-ui.badge: success #166534, warning #78350f e
           danger #b91c1c), nenhuma delas um dos dois tokens. */
        .insp-heading {
            font-size: 11pt;
            font-weight: bold;
            color: #0B1C2C;
            margin-top: 16px;
        }

        .insp-counter {
            font-size: 10pt;
            color: #5a7289;
            margin-top: 4px;
        }

        .insp-caption {
            font-size: 7.5pt;
            color: #5a7289;
            margin-top: 6px;
        }

        .insp-tone--success { color: #166534; }
        .insp-tone--warning { color: #78350f; }
        .insp-tone--danger { color: #b91c1c; }
        .insp-tone--neutral { color: #5a7289; }

        .insp-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9pt;
            border: 1px solid #d1dae4;
        }

        .insp-list td {
            padding: 6px 8px;
            border: 1px solid #d1dae4;
            vertical-align: top;
            color: #344453;
        }

        .insp-list tr:nth-child(even) td {
            background-color: #f4f7fa;
        }

        .insp-list-date {
            white-space: nowrap;
        }

        .insp-list-verdict {
            white-space: nowrap;
            font-weight: bold;
        }

        /* Só o 2º+ laudo e o 1º depois de uma OS quebram página: a capa já quebra depois de si, e
           somar as duas inseriria uma folha em branco (a mesma armadilha de .os-document). */
        .os-document + .inspection-document,
        .inspection-document + .inspection-document {
            page-break-before: always;
        }

        .inspection-document thead {
            display: table-header-group;
        }

        .inspection-document > tbody > tr > td {
            padding: 0 18px 14px;
        }

        .inspection-document > thead > tr > td {
            padding: 18px 18px 0;
        }

        .verdict {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .verdict td {
            vertical-align: middle;
            padding: 8px 10px;
        }

        .verdict td.verdict-last {
            text-align: right;
        }

        .verdict-kicker {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .verdict-label {
            font-size: 12pt;
            font-weight: bold;
            color: #0B1C2C;
            margin-top: 2px;
        }

        .verdict-meta {
            font-size: 7.5pt;
            color: #455a6e;
            margin-top: 2px;
            line-height: 1.35;
        }

        .verdict-flag {
            font-size: 8pt;
            font-weight: bold;
            color: #6b5600;
            margin-top: 4px;
            line-height: 1.35;
        }

        .verdict-code {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 10pt;
            font-weight: bold;
            color: #0B1C2C;
            letter-spacing: 1px;
        }

        .verdict-verify {
            font-size: 7pt;
            color: #5a7289;
            margin-top: 2px;
        }

        .verdict--success td {
            background-color: #f0fdf4;
            border-top: 1px solid #bbf7d0;
            border-bottom: 1px solid #bbf7d0;
        }

        .verdict--success td.verdict-first { border-left: 4px solid #166534; }
        .verdict--success td.verdict-last { border-right: 1px solid #bbf7d0; }
        .verdict--success .verdict-kicker { color: #166534; }

        .verdict--warning td {
            background-color: #fef3c7;
            border-top: 1px solid #fcd34d;
            border-bottom: 1px solid #fcd34d;
        }

        .verdict--warning td.verdict-first { border-left: 4px solid #78350f; }
        .verdict--warning td.verdict-last { border-right: 1px solid #fcd34d; }
        .verdict--warning .verdict-kicker { color: #78350f; }

        .verdict--danger td {
            background-color: #fef2f2;
            border-top: 1px solid #fecaca;
            border-bottom: 1px solid #fecaca;
        }

        .verdict--danger td.verdict-first { border-left: 4px solid #b91c1c; }
        .verdict--danger td.verdict-last { border-right: 1px solid #fecaca; }
        .verdict--danger .verdict-kicker { color: #b91c1c; }

        .verdict--neutral td {
            background-color: #f4f7fa;
            border-top: 1px solid #d1dae4;
            border-bottom: 1px solid #d1dae4;
        }

        .verdict--neutral td.verdict-first { border-left: 4px solid #5a7289; }
        .verdict--neutral td.verdict-last { border-right: 1px solid #d1dae4; }
        .verdict--neutral .verdict-kicker { color: #5a7289; }

        /* A página do laudo é cheia: a tabela de oito eixos mais o cartão de dados chega perto do pé
           da folha, e o rodapé do documento encostaria no "Página X de Y" que o exporter carimba na
           margem. Por isso o corpo do laudo respira um pouco menos que o da OS. */
        .inspection-document .meta-line {
            margin-bottom: 3px;
        }

        .inspection-document .os-body-card td {
            padding: 8px 12px 10px;
        }

        .findings-table {
            font-size: 8.5pt;
        }

        .findings-table th,
        .findings-table td {
            padding: 6px 8px;
        }

        .findings-table td.finding-status {
            white-space: nowrap;
            font-weight: bold;
        }

        .findings-caption {
            font-size: 7.5pt;
            color: #5a7289;
            margin-top: 6px;
        }

        .doc-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #d1dae4;
            text-align: center;
            font-size: 8pt;
            color: #5a7289;
        }
    </style>
</head>
<body>
    {{-- Página 1: capa RevisaLog + dados do veículo --}}
    <table class="document-table cover-document{{ $hasMaintenances || $hasInspections ? '' : ' cover-document--only' }}" cellpadding="0" cellspacing="0">
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
                            <td class="band-right" width="30%" valign="middle">Gerado em {{ now($displayTimezone)->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>

                    <div class="cover-body">
                        @if($vehicle->hasCurrentOwner() && ! $vehicle->hasVerifiedOwnership())
                            <div class="ownership-unverified">
                                <strong>Propriedade não confirmada.</strong>
                                O veículo foi cadastrado sem o CRLV-e, então ninguém confirmou de quem ele é. As
                                manutenções com Selo da oficina seguem confirmadas por quem prestou o serviço.
                            </div>
                        @endif

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
                                @if($pdfChassis)
                                <tr>
                                    <td colspan="2">
                                        <span class="info-label">Chassi:</span>
                                        <span class="info-value" style="font-family: DejaVu Sans Mono, monospace; font-size: 13px; letter-spacing: 0.05em;">{{ $pdfChassis }}</span>
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td>
                                        <span class="info-label">Placa atual:</span>
                                        <span class="info-value">{{ filled($vehicle->license_plate) ? $vehicle->license_plate : 'Placa não informada' }}</span>
                                    </td>
                                    <td>
                                        <span class="info-label">RENAVAM:</span>
                                        <span class="info-value">{{ $pdfRenavam }}</span>
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
                                        <table class="plates-table" cellpadding="4" cellspacing="0" width="100%">
                                            <tr>
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
                                @if($vehicle->motorization || $pdfEngine)
                                <tr>
                                    @if($vehicle->motorization)
                                    <td>
                                        <span class="info-label">Motorização:</span>
                                        <span class="info-value">{{ $vehicle->motorization }}</span>
                                    </td>
                                    @else
                                    <td></td>
                                    @endif
                                    @if($pdfEngine)
                                    <td>
                                        <span class="info-label">Código do motor:</span>
                                        <span class="info-value">{{ $pdfEngine }}</span>
                                    </td>
                                    @else
                                    <td></td>
                                    @endif
                                </tr>
                                @endif
                            </table>
                        </div>

                        @if($hasMaintenances)
                            @php
                                $provenanceStrip = \App\Support\VehicleProvenanceStrip::segmentsForVehicle($vehicle);
                                $totalMaintenanceCount = $vehicle->maintenances->count();
                                $sealedMaintenanceCount = $vehicle->maintenances->filter(fn ($m) => $m->isVerified())->count();
                                $declaredMaintenanceCount = $totalMaintenanceCount - $sealedMaintenanceCount;
                                $provenanceDotRows = array_chunk($provenanceStrip, 12);
                            @endphp
                            <div class="prov-heading">Procedência das manutenções</div>
                            <p class="prov-counter">
                                <span class="prov-counter--sealed">{{ number_format($sealedMaintenanceCount, 0, ',', '.') }} com selo</span>
                                ·
                                <span class="prov-counter--declared">{{ \App\Support\Vehicle\VehicleMaintenanceHistory::declaredLabel($declaredMaintenanceCount) }}</span>
                            </p>
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
                                Um ponto por manutenção, da mais antiga à mais recente:
                                <span style="color:#0f766e;">●</span> Selo da oficina (registrada pela própria oficina) ·
                                <span style="color:#92400e;">◌</span> Declarada pelo proprietário ou lojista. Detalhes nas páginas seguintes.
                            </p>
                        @else
                            <div class="empty-state">
                                <p>Nenhuma manutenção registrada para este veículo até {{ now($displayTimezone)->format('d/m/Y') }}.</p>
                            </div>
                        @endif

                        {{-- Vistorias cautelares: a outra metade do histórico, e fora das contas acima. --}}
                        @if($hasInspections)
                            <div class="insp-heading">Vistorias cautelares</div>
                            <p class="insp-counter">
                                {{ $pdfInspections->count() === 1 ? '1 laudo registrado' : $pdfInspections->count().' laudos registrados' }},
                                do mais recente ao mais antigo.
                            </p>
                            <table class="insp-list" cellpadding="0" cellspacing="0">
                                @foreach($pdfInspections as $coverInspection)
                                    <tr>
                                        <td class="insp-list-date" width="18%">{{ $coverInspection->inspected_at->format('d/m/Y') }}</td>
                                        <td class="insp-list-verdict insp-tone--{{ $coverInspection->verdict?->badgeVariant() ?? 'neutral' }}" width="34%">
                                            {{ $coverInspection->verdict?->label() ?? 'Sem veredito' }}@if($coverInspection->isExpired()) · vencido @endif
                                        </td>
                                        <td class="insp-list-issuer">{{ $coverInspection->provenance_sublabel }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p class="insp-caption">
                                A vistoria afirma a condição do carro num dia; a manutenção registra o que foi feito
                                nele. Por isso o laudo não entra na contagem de manutenções, e cada um tem a sua
                                página no fim deste documento.
                            </p>
                        @endif
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    @if($hasMaintenances)
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
                            @if($maintenance->isDisputed())
                                <div class="seal-meta" style="padding: 4px 0; font-weight: bold;">
                                    Contestada pelo proprietário: ele diz que este serviço não foi feito no carro dele. O registro permanece no histórico do chassi com esta ressalva.
                                </div>
                            @endif
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
                                                Registro feito pela própria oficina em {{ $maintenance->verified_at?->copy()->timezone($displayTimezone)->format('d/m/Y') }}
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
                                                    <span class="info-value">{{ \App\Enums\ServiceCategory::labelFor($maintenance->service_category) ?? 'Outros' }}</span>
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
                                                        <br><span class="item-detail">{{ $item->description }}</span>
                                                    @endif
                                                    @if($item->part_number)
                                                        <br><span class="item-detail">Código: {{ $item->part_number }}</span>
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
                    @php $osPhotos = ($maintenancePhotos ?? [])[$maintenance->id] ?? null; @endphp
                    @if($osPhotos !== null && $osPhotos['items'] !== [])
                        <tr>
                            <td>
                                <div class="section-heading">Fotos do serviço</div>
                                <table class="photo-grid" cellpadding="0" cellspacing="0">
                                    @foreach(array_chunk($osPhotos['items'], 2) as $photoRow)
                                        <tr>
                                            @foreach($photoRow as $photo)
                                                <td>
                                                    <img src="{{ $photo['src'] }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['label'] }}">
                                                    <div class="photo-caption">{{ $photo['label'] }}</div>
                                                </td>
                                            @endforeach
                                            @if(count($photoRow) < 2)
                                                <td></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </table>
                                @if($osPhotos['extra'] > 0)
                                    <div class="photo-more">
                                        +{{ $osPhotos['extra'] }} {{ $osPhotos['extra'] === 1 ? 'foto' : 'fotos' }} no RevisaLog{{ $maintenance->verification_code ? ' · revisalog.com.br/v/'.$maintenance->verification_code : '' }}
                                    </div>
                                @endif
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
    @endif

    {{--
        Uma página por laudo, depois das OS. O laudo chega redigido: sem resumo e sem observação de
        eixo quer dizer que este leitor só tem a forma mínima, e aí a coluna "Observação" não é
        desenhada em vez de sair uma coluna de travessões.
    --}}
    @if($hasInspections)
        @foreach($pdfInspections as $inspection)
            @php
                $inspectionTone = $inspection->verdict?->badgeVariant() ?? 'neutral';
                $inspectionVerdictLabel = $inspection->verdict?->label() ?? 'Sem veredito';
                $inspectionIssuer = $inspection->provenance_sublabel;
                $inspectionDate = $inspection->inspected_at->format('d/m/Y');
                $inspectionLogo = ($inspectionWorkshopLogos ?? [])[$inspection->id] ?? null;
                $inspectionFindings = [];
                foreach (\App\Enums\InspectionFinding::cases() as $axis) {
                    $inspectionFindings[] = [
                        'label' => $axis->label(),
                        'status' => \App\Support\Inspection\InspectionFindings::statusOf($inspection->findings, $axis),
                        'note' => \App\Support\Inspection\InspectionFindings::noteOf($inspection->findings, $axis),
                    ];
                }
                $inspectionHasNotes = collect($inspectionFindings)->contains(fn (array $finding): bool => filled($finding['note']));
            @endphp

            <table class="document-table inspection-document" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <td>
                            <table class="letterhead" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="75%" valign="top" style="padding:8px 10px;">
                                        @if($inspection->workshop)
                                            <div class="letterhead-name">{{ $inspection->workshop->name }}</div>
                                            <div class="letterhead-line">{{ $inspection->workshop->full_address }}</div>
                                            @if($inspection->workshop->phone)
                                                <div class="letterhead-line">Telefone: {{ $inspection->workshop->phone }}</div>
                                            @endif
                                            @if($inspection->workshop->whatsapp)
                                                <div class="letterhead-line">WhatsApp: {{ $inspection->workshop->whatsapp }}</div>
                                            @endif
                                            @if($inspection->workshop->email)
                                                <div class="letterhead-line">E-mail: {{ $inspection->workshop->email }}</div>
                                            @endif
                                        @else
                                            <div class="letterhead-name">{{ $inspectionIssuer }}</div>
                                        @endif
                                    </td>
                                    <td width="25%" class="letterhead-logo-cell" style="padding:8px 10px;">
                                        @if(! empty($inspectionLogo))
                                            <img
                                                src="{{ $inspectionLogo }}"
                                                alt=""
                                                class="letterhead-logo"
                                                width="{{ $workshopLogoWidth ?? 120 }}"
                                                height="{{ $workshopLogoHeight ?? 130 }}"
                                            >
                                        @elseif(! $inspection->workshop && ! empty($revisalogLogoSrc))
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
                                    <td class="band-left" width="70%" valign="middle">Vistoria cautelar</td>
                                    <td class="band-right" width="30%" valign="middle">{{ $inspectionDate }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <table class="verdict verdict--{{ $inspectionTone }}" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="verdict-first" width="62%" valign="middle">
                                        <div class="verdict-kicker">Veredito da vistoria</div>
                                        <div class="verdict-label">{{ $inspectionVerdictLabel }}</div>
                                        <div class="verdict-meta">
                                            {{ $inspectionIssuer }} examinou este veículo em {{ $inspectionDate }} e
                                            registrou o laudo na RevisaLog.
                                        </div>
                                        @if($inspection->isExpired())
                                            <div class="verdict-flag">
                                                Validade terminada em {{ $inspection->valid_until->format('d/m/Y') }}.
                                                O laudo afirma a condição do carro no dia da vistoria, e ele rodou
                                                desde então.
                                            </div>
                                        @endif
                                        @if($inspection->isDisputed())
                                            <div class="verdict-flag">Contestado pelo proprietário.</div>
                                        @endif
                                    </td>
                                    <td class="verdict-last" width="38%" valign="middle">
                                        @if($inspection->verification_code)
                                            <div class="verdict-code">{{ $inspection->verification_code }}</div>
                                            <div class="verdict-verify">revisalog.com.br/laudo/{{ $inspection->verification_code }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <table class="os-body-card" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div class="meta-block">
                                            <div class="meta-line">
                                                <span class="info-label">Emissor:</span>
                                                <span class="info-value">{{ $inspectionIssuer }}</span>
                                            </div>
                                            <div class="meta-line">
                                                <span class="info-label">Data da vistoria:</span>
                                                <span class="info-value">{{ $inspectionDate }}</span>
                                            </div>
                                            <div class="meta-line">
                                                <span class="info-label">Validade:</span>
                                                <span class="info-value">{{ $inspection->valid_until ? $inspection->valid_until->format('d/m/Y') : 'sem validade declarada' }}</span>
                                            </div>
                                            @if($inspection->odometer_km !== null)
                                                <div class="meta-line">
                                                    <span class="info-label">Hodômetro lido na vistoria:</span>
                                                    <span class="info-value">{{ number_format((int) $inspection->odometer_km, 0, ',', '.') }} km</span>
                                                </div>
                                            @endif
                                            @if(filled($inspection->summary))
                                                <div class="meta-line" style="margin-top: 8px;">
                                                    <span class="info-label">Resumo do vistoriador:</span>
                                                    <div class="info-value" style="margin-top: 4px;">{{ $inspection->summary }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="section-heading">Achados da vistoria</div>
                            <table class="items-table findings-table" cellpadding="0" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="{{ $inspectionHasNotes ? '34%' : '60%' }}">Item examinado</th>
                                        <th width="{{ $inspectionHasNotes ? '26%' : '40%' }}">Situação</th>
                                        @if($inspectionHasNotes)
                                            <th width="40%">Observação</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($inspectionFindings as $finding)
                                        <tr>
                                            <td>{{ $finding['label'] }}</td>
                                            <td class="finding-status insp-tone--{{ $finding['status']->badgeVariant() }}">{{ $finding['status']->label() }}</td>
                                            @if($inspectionHasNotes)
                                                <td>{{ filled($finding['note']) ? $finding['note'] : '—' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="findings-caption">
                                "Não avaliado" quer dizer que a vistoria não olhou aquele item — não que ele esteja em
                                ordem.
                                @if(filled($inspection->report_path))
                                    O laudo completo em PDF fica na RevisaLog, na página deste veículo.
                                @endif
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endforeach
    @endif

    <div class="doc-footer">
        <p>Confira qualquer Selo da oficina em revisalog.com.br/verificar ou em revisalog.com.br/v/{código}</p>
        @if($hasInspections)
            <p>Confira qualquer vistoria cautelar em revisalog.com.br/laudo/{código}</p>
        @endif
        <p>Relatório gerado automaticamente pela RevisaLog (revisalog.com.br) em {{ now($displayTimezone)->format('d/m/Y') }} às {{ now($displayTimezone)->format('H:i') }}</p>
    </div>
</body>
</html>
