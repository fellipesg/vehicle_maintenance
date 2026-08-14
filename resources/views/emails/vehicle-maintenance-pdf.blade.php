<p>Olá,</p>

<p>
    Segue o relatório de manutenções do veículo
    <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong>
    (placa {{ $vehicle->license_plate }}).
    O histórico consolidado está no PDF principal.
    @if(count($invoiceAttachments) > 0)
        As notas fiscais também seguem como arquivos anexos neste e-mail.
    @endif
</p>

@if(count($invoiceLinks) > 0)
    <p>Links para baixar as notas (válidos por alguns dias):</p>
    <ul>
        @foreach($invoiceLinks as $invoice)
            <li><a href="{{ $invoice['url'] }}">{{ $invoice['filename'] }}</a></li>
        @endforeach
    </ul>
@endif

<p>Este e-mail foi gerado automaticamente pelo Vehicle Maintenance.</p>
