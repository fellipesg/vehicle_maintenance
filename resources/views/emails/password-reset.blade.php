<x-mail::message>
# {{ $firstName !== '' ? "Olá, {$firstName}!" : 'Olá!' }}

Recebemos um pedido para redefinir a senha da sua conta na RevisaLog. Para criar uma senha nova, use o botão abaixo.

<x-mail::button :url="$resetUrl">
Criar nova senha
</x-mail::button>

O link vale por {{ $expiresInMinutes }} minutos e só pode ser usado uma vez.

Se não foi você que pediu, ignore este e-mail: sua senha continua a mesma.

{{ $salutation }}

<x-slot:subcopy>
Se o botão não funcionar, copie e cole este endereço no navegador: <span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
