<x-mail::message>
# Contato pelo site

**Nome:** {{ $name }}  
**E-mail:** {{ $email }}  
@if($topic)
**Assunto:** {{ $topic }}
@endif

{{ $body }}

RevisaLog
</x-mail::message>
