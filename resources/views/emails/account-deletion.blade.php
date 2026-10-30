<x-mail::message>
# Confirme a exclusão da sua conta

Recebemos um pedido para excluir sua conta RevisaLog e os dados pessoais ligados a ela.

**Nada foi apagado ainda.** A exclusão só acontece depois que você confirmar pelo botão abaixo.

<x-mail::button :url="$confirmationUrl">
Confirmar exclusão
</x-mail::button>

O link vale por {{ $expiresInMinutes }} minutos e só pode ser usado uma vez.

Ao confirmar, apagamos seu nome, e-mail, telefone, documento, endereço, foto de perfil, senha e os
dispositivos que recebem notificações, e desfazemos o vínculo entre você e seus veículos. O histórico
de manutenções continua ligado ao chassi do veículo, sem nenhuma ligação com você.

Se não foi você que pediu, ignore esta mensagem: sua conta fica como está. Em caso de dúvida, escreva
para {{ $supportEmail }}.

Obrigado,<br>
RevisaLog
</x-mail::message>
