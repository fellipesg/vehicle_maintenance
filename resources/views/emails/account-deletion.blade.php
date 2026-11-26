{{--
    Confirmação da exclusão de conta pedida em /excluir-conta.

    $confirmations traz um link por perfil do e-mail (App\Mail\AccountDeletionRequestMail): com um
    perfil só é o botão de sempre; com contas irmãs, um botão nomeado para cada, porque cada perfil
    é excluído separadamente e os outros continuam.
--}}
<x-mail::message>
# Confirme a exclusão da sua conta

Recebemos um pedido para excluir sua conta RevisaLog e os dados pessoais ligados a ela.

**Nada foi apagado ainda.** A exclusão só acontece depois que você confirmar pelo botão abaixo.

@if(count($confirmations) === 1)
<x-mail::button :url="$confirmations[0]['url']">
Confirmar exclusão
</x-mail::button>
@else
Este e-mail tem mais de um perfil na RevisaLog, e cada um é excluído separadamente. Escolha qual
você quer excluir — os outros continuam como estão.

@foreach($confirmations as $confirmation)
<x-mail::button :url="$confirmation['url']">
Excluir {{ $confirmation['label'] }}
</x-mail::button>
@endforeach
@endif

O link vale por {{ $expiresInMinutes }} minutos e só pode ser usado uma vez.

Ao confirmar, apagamos seu nome, e-mail, telefone, documento, endereço, foto de perfil, senha e os
dispositivos que recebem notificações, e desfazemos o vínculo entre você e seus veículos. O histórico
de manutenções continua ligado ao chassi do veículo, sem nenhuma ligação com você.

Se não foi você que pediu, ignore esta mensagem: sua conta fica como está. Em caso de dúvida, escreva
para {{ $supportEmail }}.

Obrigado,<br>
RevisaLog
</x-mail::message>
