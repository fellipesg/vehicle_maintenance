@if($followUp)
{!! $saudacao !!}

Passando só para saber se a mensagem anterior chegou. Se a oficina quiser aparecer com o próprio nome no histórico dos carros que atende, no lançamento o RevisaLog é sem custo: {!! $link !!}

Se não for o momento, tudo bem. Não envio mais nada.

Abraço,
{!! $assinatura !!}

—
Para não receber mensagens do RevisaLog: {!! $sair !!}
@else
{!! $saudacao !!}

Sou o {!! $assinatura !!}, do RevisaLog. Queremos que, no Brasil, o histórico de manutenção fique com o carro: registrado pelo chassi, e não pela placa nem pelo dono. Quando o carro é vendido, a história vai junto.

Quando um cliente registra um serviço feito na sua oficina, vocês podem confirmar o registro, e ele ganha o Selo da oficina, com o nome de vocês. Cada Selo tem um código que qualquer pessoa confere em revisalog.com.br/verificar. A oficina também aparece na busca de oficinas do app e pode mandar lembretes de revisão para os clientes.

No lançamento, a oficina usa o RevisaLog sem custo. Se no futuro houver planos pagos, avisamos com antecedência, e nada é cobrado sem a oficina contratar.

Se fizer sentido, é só responder este e-mail ou deixar seu contato aqui: {!! $link !!}

Abraço,
{!! $assinatura !!}
RevisaLog · revisalog.com.br

—
Encontramos este e-mail no cadastro público de CNPJ da Receita Federal, na atividade de manutenção de veículos. Se não quiser receber mensagens do RevisaLog, clique aqui: {!! $sair !!}
@endif
