<p>Olá,</p>

<p>
    Segue o relatório de manutenções do veículo
    <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong>
    (placa {{ $vehicle->license_plate }}).
    O histórico consolidado está no PDF principal
    @if(count($invoiceAttachments) > 0)
        e cada nota fiscal segue como arquivo anexo neste e-mail.
    @else
        .
    @endif
</p>

<p>Este e-mail foi gerado automaticamente pelo Vehicle Maintenance.</p>
