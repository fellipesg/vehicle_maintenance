{{--
    "Avisar o cliente" na OS de um carro sem proprietário. Só a oficina que fez a OS vê
    (MaintenanceController::show, MaintenanceInviteService::canInvite). Dois caminhos:

    - WhatsApp: a oficina digita o telefone, o servidor guarda só a data e abre o wa.me com a
      mensagem pronta (App\Support\Maintenance\CustomerInviteMessage); a oficina envia do próprio
      celular. A mensagem não leva placa nem chassi.
    - E-mail: um único e-mail por OS, pelo mailer padrão, com link de descadastro.

    Variáveis: $maintenance e $invite (App\Models\MaintenanceInvite ou null).
--}}
@php
    $inviteEmailSent = $invite?->email_invited_at !== null;
    $inviteWhatsappAt = $invite?->whatsapp_invited_at ? \App\Support\DisplayTime::local($invite->whatsapp_invited_at) : null;
@endphp

<x-ui.card as="section" class="mb-6" heading-level="h2" title="Avisar o cliente"
    description="Este carro ainda não tem proprietário no RevisaLog. O cliente recebe um link com a oficina, o modelo do carro e a data do serviço, e cria uma conta grátis. Nada é vinculado sem a escolha dele." data-invite-card>
    <div class="grid gap-6 md:grid-cols-2">
        <form method="POST" action="{{ route('workshop.maintenances.invite.whatsapp', $maintenance) }}" target="_blank" class="space-y-3" data-invite-whatsapp data-submit-busy="off">
            @csrf
            <x-ui.field name="phone" bag="inviteWhatsapp" label="Telefone do cliente (WhatsApp)" hint="Com DDD. A mensagem abre pronta no seu WhatsApp; nós não guardamos o número." required>
                <x-ui.input id="invite-phone" type="tel" inputmode="tel" autocomplete="off" required maxlength="30" placeholder="(11) 91234-5678" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary" icon="chat-bubble-left-right" class="max-sm:w-full">Abrir WhatsApp</x-ui.button>
            @if($inviteWhatsappAt)
                <p class="text-sm text-muted-foreground" data-invite-whatsapp-at>Aberto em {{ $inviteWhatsappAt->format('d/m/Y') }} às {{ $inviteWhatsappAt->format('H:i') }}.</p>
            @endif
        </form>

        <form method="POST" action="{{ route('workshop.maintenances.invite.email', $maintenance) }}" class="space-y-3" data-invite-email>
            @csrf
            @if($inviteEmailSent)
                <x-ui.alert variant="success" role="status" title="Convite por e-mail enviado" data-invite-email-sent>
                    Cada OS aceita um só e-mail de convite. Para falar de novo com o cliente, use o WhatsApp.
                </x-ui.alert>
            @else
                <x-ui.field name="email" bag="inviteEmail" label="E-mail do cliente" hint="Enviamos um único e-mail. Guardamos só uma marca irreversível do endereço, não o endereço." required>
                    <x-ui.input id="invite-email" type="email" inputmode="email" autocomplete="off" required maxlength="255" placeholder="cliente@exemplo.com.br" />
                </x-ui.field>
                <x-ui.button type="submit" variant="secondary" icon="envelope" loading-label="Enviando…" class="max-sm:w-full">Enviar convite por e-mail</x-ui.button>
            @endif
        </form>
    </div>
</x-ui.card>
