<x-mail::message>
# Histórico do {{ $vehicleName }}

Segue o histórico de manutenções do **{{ $vehicleName }}** (placa {{ $plate }}), gerado em {{ $generatedAt }}.

@if($maintenanceCount > 0)
**{{ $provenanceSummary }}.** Com selo são as manutenções registradas pela própria oficina; declaradas são as informadas pelo proprietário ou pelo lojista.
@else
O veículo ainda não tem manutenções registradas. O PDF traz os dados do veículo.
@endif

## Anexos

@foreach($attachmentLines as $attachmentLine)
- {{ $attachmentLine }}
@endforeach

<x-mail::button :url="$vehicleUrl">
Ver veículo na RevisaLog
</x-mail::button>

Quem receber o relatório pode conferir qualquer Selo da oficina em [{{ preg_replace('#^https?://#', '', $verificationUrl) }}]({{ $verificationUrl }}).

RevisaLog
</x-mail::message>
