<x-mail::message>
# Olá, {{ $firstName }}!

A RevisaLog guarda o histórico de manutenções **no veículo**, não na sua conta. Assim o registro acompanha o carro mesmo quando ele muda de dono.

## Por onde começar

1. **Adicione seu veículo** pelo CRLV-e ou com placa, chassi e RENAVAM.
2. **Registre as manutenções** que você já fez. Elas entram no histórico como **Declarada pelo proprietário**.
3. **Peça o Selo da oficina** quando o serviço for numa oficina cadastrada: a própria oficina registra a manutenção, e o registro fica confirmado.

@if($needsPassword)
Você criou a conta no app com o Google ou o Facebook, então ela ainda não tem senha. Continue usando o app normalmente. Para entrar também pelo navegador, defina uma senha para o e-mail **{{ $email }}**.
@elseif($createdInApp)
Você criou a conta no app. No navegador, entre com o mesmo e-mail e a mesma senha.
@endif

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Dúvidas? Responda este e-mail: falamos com você em {{ config('mail.reply_to.address') }}.

RevisaLog
</x-mail::message>
