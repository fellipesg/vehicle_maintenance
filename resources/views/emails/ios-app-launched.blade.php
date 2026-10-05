<x-mail::message>
# Olá, {{ $firstName }}!

O RevisaLog agora tem app para iPhone, na App Store. Pelo celular você consulta o histórico dos seus veículos, registra manutenções e acompanha quais serviços têm o Selo da oficina.

Entre com a mesma conta que você já usa no site.

<x-mail::button :url="$appStoreUrl">
Baixar na App Store
</x-mail::button>

A versão para Android está a caminho. Avisamos quando chegar.

Dúvidas? Responda este e-mail: falamos com você em {{ config('mail.reply_to.address') }}.

RevisaLog

<x-mail::subcopy>
Você recebe este aviso único porque tem uma conta no RevisaLog.
</x-mail::subcopy>
</x-mail::message>
