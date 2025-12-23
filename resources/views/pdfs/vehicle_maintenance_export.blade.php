<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        .header {
            background-color: #1e40af;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18pt;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 9pt;
            opacity: 0.9;
        }
        
        .vehicle-info {
            background-color: #f3f4f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .vehicle-info h2 {
            font-size: 14pt;
            margin-bottom: 10px;
            color: #1e40af;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 8px;
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
        
        .maintenance-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .maintenance-header {
            background-color: #3b82f6;
            color: white;
            padding: 12px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }
        
        .maintenance-header h3 {
            font-size: 12pt;
            margin-bottom: 5px;
        }
        
        .maintenance-content {
            border: 1px solid #e5e7eb;
            border-top: none;
            padding: 15px;
            border-radius: 0 0 5px 5px;
        }
        
        .maintenance-details {
            margin-bottom: 15px;
        }
        
        .maintenance-details .info-item {
            margin-bottom: 6px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        
        .items-table th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: left;
            border: 1px solid #e5e7eb;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .invoices-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .invoices-section h4 {
            font-size: 11pt;
            margin-bottom: 10px;
            color: #1e40af;
        }
        
        .invoice-item {
            padding: 8px;
            background-color: #f9fafb;
            margin-bottom: 8px;
            border-left: 3px solid #3b82f6;
            padding-left: 12px;
        }
        
        .invoice-item strong {
            color: #1e40af;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        
        .badge-preventive {
            background-color: #10b981;
            color: white;
        }
        
        .badge-corrective {
            background-color: #ef4444;
            color: white;
        }
        
        .badge-inspection {
            background-color: #f59e0b;
            color: white;
        }
        
        .badge-other {
            background-color: #6b7280;
            color: white;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Histórico de Manutenções</h1>
        <p>Relatório gerado em {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    
    <div class="vehicle-info">
        <h2>Informações do Veículo</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Marca/Modelo:</span>
                <span class="info-value">{{ $vehicle->brand }} {{ $vehicle->model }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Ano:</span>
                <span class="info-value">{{ $vehicle->year }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Placa:</span>
                <span class="info-value">{{ $vehicle->license_plate }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">RENAVAM:</span>
                <span class="info-value">{{ $vehicle->renavam }}</span>
            </div>
            @if($vehicle->color)
            <div class="info-item">
                <span class="info-label">Cor:</span>
                <span class="info-value">{{ $vehicle->color }}</span>
            </div>
            @endif
            @if($vehicle->chassis)
            <div class="info-item">
                <span class="info-label">Chassi:</span>
                <span class="info-value">{{ $vehicle->chassis }}</span>
            </div>
            @endif
            @if($vehicle->engine)
            <div class="info-item">
                <span class="info-label">Motor:</span>
                <span class="info-value">{{ $vehicle->engine }}</span>
            </div>
            @endif
        </div>
    </div>
    
    @if($vehicle->maintenances->count() > 0)
        <h2 style="font-size: 14pt; margin-bottom: 15px; color: #1e40af;">Manutenções Realizadas ({{ $vehicle->maintenances->count() }})</h2>
        
        @foreach($vehicle->maintenances as $index => $maintenance)
            <div class="maintenance-section {{ $index > 0 ? 'page-break' : '' }}">
                <div class="maintenance-header">
                    <h3>
                        @if($maintenance->maintenance_type === 'preventive')
                            <span class="badge badge-preventive">PREVENTIVA</span>
                        @elseif($maintenance->maintenance_type === 'corrective')
                            <span class="badge badge-corrective">CORRETIVA</span>
                        @elseif($maintenance->maintenance_type === 'inspection')
                            <span class="badge badge-inspection">INSPEÇÃO</span>
                        @else
                            <span class="badge badge-other">OUTRA</span>
                        @endif
                        - {{ \Carbon\Carbon::parse($maintenance->maintenance_date)->format('d/m/Y') }}
                    </h3>
                </div>
                
                <div class="maintenance-content">
                    <div class="maintenance-details">
                        <div class="info-item">
                            <span class="info-label">Data:</span>
                            <span class="info-value">{{ \Carbon\Carbon::parse($maintenance->maintenance_date)->format('d/m/Y') }}</span>
                        </div>
                        @if($maintenance->kilometers)
                        <div class="info-item">
                            <span class="info-label">Quilometragem:</span>
                            <span class="info-value">{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</span>
                        </div>
                        @endif
                        @if($maintenance->service_category)
                        <div class="info-item">
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
                        @if($maintenance->workshop || $maintenance->workshop_name)
                        <div class="info-item" style="margin-top: 10px; padding: 10px; background-color: #f9fafb; border-left: 3px solid #3b82f6;">
                            <div style="margin-bottom: 8px;">
                                <span class="info-label">Oficina:</span>
                                <span class="info-value" style="font-weight: bold; font-size: 11pt;">{{ $maintenance->workshop ? $maintenance->workshop->name : $maintenance->workshop_name }}</span>
                            </div>
                            @if($maintenance->workshop)
                                <div style="margin-top: 8px; font-size: 9pt;">
                                    @if($maintenance->workshop->phone)
                                    <div style="margin-bottom: 4px;">
                                        <strong>Telefone:</strong> {{ $maintenance->workshop->phone }}
                                    </div>
                                    @endif
                                    @if($maintenance->workshop->whatsapp)
                                    <div style="margin-bottom: 4px;">
                                        <strong>WhatsApp:</strong> {{ $maintenance->workshop->whatsapp }}
                                    </div>
                                    @endif
                                    @if($maintenance->workshop->email)
                                    <div style="margin-bottom: 4px;">
                                        <strong>Email:</strong> <a href="mailto:{{ $maintenance->workshop->email }}" style="color: #3b82f6;">{{ $maintenance->workshop->email }}</a>
                                    </div>
                                    @endif
                                    <div style="margin-bottom: 4px;">
                                        <strong>Endereço:</strong> {{ $maintenance->workshop->street }}, {{ $maintenance->workshop->number }}
                                        @if($maintenance->workshop->complement)
                                            - {{ $maintenance->workshop->complement }}
                                        @endif
                                        - {{ $maintenance->workshop->neighborhood }}, {{ $maintenance->workshop->city }}/{{ $maintenance->workshop->state }}
                                        - CEP: {{ preg_replace('/(\d{5})(\d{3})/', '$1-$2', $maintenance->workshop->cep) }}
                                    </div>
                                    @if($maintenance->workshop->facebook || $maintenance->workshop->instagram)
                                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e5e7eb;">
                                        <strong>Redes Sociais:</strong>
                                        @if($maintenance->workshop->facebook)
                                        <div style="margin-top: 4px;">
                                            <span style="color: #1877f2;">📘 Facebook:</span> <a href="{{ $maintenance->workshop->facebook }}" style="color: #3b82f6; text-decoration: underline;">{{ $maintenance->workshop->facebook }}</a>
                                        </div>
                                        @endif
                                        @if($maintenance->workshop->instagram)
                                        <div style="margin-top: 4px;">
                                            <span style="color: #e4405f;">📷 Instagram:</span> <a href="{{ $maintenance->workshop->instagram }}" style="color: #3b82f6; text-decoration: underline;">{{ $maintenance->workshop->instagram }}</a>
                                        </div>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @endif
                        @if($maintenance->is_manufacturer_required)
                        <div class="info-item">
                            <span class="info-label">Tipo:</span>
                            <span class="info-value">Exigida pelo fabricante</span>
                        </div>
                        @endif
                        @if($maintenance->description)
                        <div class="info-item" style="margin-top: 10px;">
                            <span class="info-label">Descrição:</span>
                            <div class="info-value" style="margin-top: 5px;">{{ $maintenance->description }}</div>
                        </div>
                        @endif
                    </div>
                    
                    @if($maintenance->items && $maintenance->items->count() > 0)
                        <h4 style="font-size: 11pt; margin: 15px 0 10px 0; color: #1e40af;">Itens da Manutenção</h4>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantidade</th>
                                    <th>Preço Unit.</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($maintenance->items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->name }}</strong>
                                        @if($item->description)
                                        <br><small style="color: #666;">{{ $item->description }}</small>
                                        @endif
                                        @if($item->part_number)
                                        <br><small style="color: #999;">Código: {{ $item->part_number }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $item->quantity }}x</td>
                                    <td>R$ {{ number_format($item->unit_price ?? 0, 2, ',', '.') }}</td>
                                    <td><strong>R$ {{ number_format($item->total_price ?? 0, 2, ',', '.') }}</strong></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    
                    @if($maintenance->invoices && $maintenance->invoices->count() > 0)
                        <div class="invoices-section">
                            <h4>Notas Fiscais</h4>
                            @foreach($maintenance->invoices as $invoice)
                                <div class="invoice-item">
                                    <strong>{{ $invoice->file_name }}</strong><br>
                                    @if($invoice->invoice_number)
                                        <small>Número: {{ $invoice->invoice_number }}</small><br>
                                    @endif
                                    @if($invoice->invoice_date)
                                        <small>Data: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</small><br>
                                    @endif
                                    @if($invoice->total_amount)
                                        <small>Valor: R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>Nenhuma manutenção registrada para este veículo.</p>
        </div>
    @endif
    
    <div class="footer">
        <p>Este relatório foi gerado automaticamente pelo sistema Vehicle Maintenance</p>
        <p>Para mais informações, acesse o sistema ou entre em contato com o suporte.</p>
    </div>
</body>
</html>

