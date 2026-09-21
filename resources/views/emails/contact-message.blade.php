<x-mail::message>
# Contato pelo site

**Nome:** {{ $name }}  
**E-mail:** {{ $email }}

{{ $body }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
