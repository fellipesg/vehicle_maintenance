<!doctype html>
<html lang="pt-BR">
<body style="font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; color: #222;">
@if($followUp)
<p>Olá, equipe da {{ $nome }}.</p>

<p>Passando só para saber se a mensagem anterior chegou. Se a oficina quiser aparecer com o próprio nome no histórico dos carros que atende, o cadastro é gratuito: <a href="{{ $link }}">{{ $link }}</a></p>

<p>Se não for o momento, tudo bem. Não envio mais nada.</p>

<p>Abraço,<br>{{ $assinatura }}</p>

<p style="color: #666; font-size: 13px;">—<br>Para não receber mensagens do RevisaLog: <a href="{{ $sair }}">{{ $sair }}</a></p>
@else
<p>Olá, equipe da {{ $nome }}.</p>

<p>Sou o {{ $assinatura }}, do RevisaLog, um app em que donos de carro guardam o histórico de manutenção do veículo.</p>

<p>Quando um cliente registra um serviço feito na sua oficina, vocês podem confirmar o registro, e ele ganha o Selo da oficina, com o nome de vocês. Cada Selo tem um código que qualquer pessoa confere em revisalog.com.br/verificar. A oficina também aparece na busca de oficinas do app e pode mandar lembretes de revisão para os clientes.</p>

<p>O cadastro da oficina é gratuito. Se fizer sentido, é só responder este e-mail ou deixar seu contato aqui: <a href="{{ $link }}">{{ $link }}</a></p>

<p>Abraço,<br>{{ $assinatura }}<br>RevisaLog · revisalog.com.br</p>

<p style="color: #666; font-size: 13px;">—<br>Encontramos este e-mail no cadastro público de CNPJ da Receita Federal, na atividade de manutenção de veículos. Se não quiser receber mensagens do RevisaLog, clique aqui: <a href="{{ $sair }}">{{ $sair }}</a></p>
@endif
</body>
</html>
