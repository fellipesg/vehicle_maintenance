<x-mail::message>
# Olá, {{ $firstName }}!

A Revisalog guarda o histórico de manutenções **no veículo**, não na sua conta. Assim o registro acompanha o carro mesmo se o dono mudar.

<x-mail::button :url="$actionUrl">
Acessar a Revisalog
</x-mail::button>

Dúvidas? Responda este e-mail — falamos com você em {{ config('mail.reply_to.address') }}.

Revisalog
</x-mail::message>
