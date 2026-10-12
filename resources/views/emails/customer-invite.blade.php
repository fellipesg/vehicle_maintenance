<x-mail::message>
# Olá!

A oficina **{{ $workshopName }}** registrou um serviço no seu **{{ $vehicleLabel }}** em {{ $serviceDate }}, e o registro está no RevisaLog.

Crie sua conta grátis para ver o serviço, guardar o histórico do carro e decidir o que entra nele. Nada é vinculado à sua conta sem a sua escolha.

<x-mail::button :url="$inviteUrl">
Ver o registro
</x-mail::button>

<x-mail::subcopy>
Você recebeu esta mensagem porque a oficina {{ $workshopName }} registrou um serviço no seu carro. Enviamos um único e-mail por serviço.
Não quer receber? [Não receber mais mensagens do RevisaLog]({{ $unsubscribeUrl }}).
</x-mail::subcopy>
</x-mail::message>
