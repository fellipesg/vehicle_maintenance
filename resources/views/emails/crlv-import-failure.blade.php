<x-mail::message>
# CRLV-e não lido

Um documento enviado pelo site não foi reconhecido pelo leitor de CRLV-e.

**Motivo:** {{ $reason }}

@if($contextRows !== [])
<x-mail::table>
| Campo | Valor |
| :---- | :---- |
@foreach($contextRows as $contextRow)
| {{ $contextRow['label'] }} | {{ $contextRow['value'] }} |
@endforeach
</x-mail::table>
@endif

Vale conferir o layout do DETRAN de origem: cada falha dessas costuma ser um modelo de documento que o leitor ainda não cobre.

RevisaLog
</x-mail::message>
