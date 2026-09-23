<x-mail::message>
# CRLV-e não lido

Um documento enviado pelo site não foi reconhecido pelo leitor de CRLV-e.

**Motivo:** {{ $reason }}

@foreach($context as $label => $value)
@if($value !== null)
**{{ ucfirst(str_replace('_', ' ', $label)) }}:** {{ $value }}
@endif
@endforeach

Vale conferir o layout do DETRAN de origem: cada falha dessas costuma ser um
modelo de documento que o leitor ainda não cobre.

Revisalog
</x-mail::message>
