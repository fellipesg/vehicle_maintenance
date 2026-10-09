<x-mail::message>
# Olá, {{ $firstName }}!

A conta da sua oficina na RevisaLog está pronta. A RevisaLog guarda o histórico de manutenções **no veículo**, e o Selo da oficina coloca o nome da sua oficina nesse histórico.

## Por onde começar

1. **Confira o perfil da oficina**: nome, logo, contato e endereço aparecem para os clientes em Oficinas da rede.
2. **Registre a primeira OS** do veículo de um cliente. Com ela, o serviço recebe o Selo da oficina e um código de conferência.
3. **Veja a fila de validação** quando um cliente registrar um serviço feito na oficina.

No lançamento a RevisaLog não cobra nada. Se no futuro houver planos pagos, avisamos com antecedência, e nada é cobrado sem a oficina contratar.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Dúvidas? Responda este e-mail: falamos com você em {{ config('mail.reply_to.address') }}.

RevisaLog
</x-mail::message>
