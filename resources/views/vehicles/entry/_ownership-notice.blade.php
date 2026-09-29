{{--
    Aviso do passo Conferir no Lojista: o que acontece com a posse antes de confirmar. Três casos,
    com textos diferentes porque pedem ações diferentes (VehicleEntryFlow::ownershipFor()):
    CRLV-e no CPF/CNPJ da conta, conta sem CNPJ e CRLV-e de outra pessoa. No Proprietário não aparece.

    Variáveis: $flow, $ownership.
--}}
@if($flow->isDealer())
    @switch($ownership)
        @case(\App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_OWNER)
            <x-ui.alert variant="success" title="O CRLV-e está no CPF/CNPJ da sua conta" data-ownership-notice="{{ $ownership }}">
                O veículo entra no estoque como da loja.
            </x-ui.alert>
            @break
        @case(\App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT)
            <x-ui.alert variant="warning" role="status" title="Sua conta não tem o CNPJ da loja" data-ownership-notice="{{ $ownership }}">
                Sem ele, não conseguimos confirmar que o veículo é da loja: ao continuar, ele entra em consignação e pede a procuração do proprietário. Se o veículo for da loja, <a href="{{ route('contact.show') }}">fale com a equipe</a> para incluir o CNPJ antes de continuar.
            </x-ui.alert>
            @break
        @default
            <x-ui.alert variant="info" title="CRLV-e em nome de outra pessoa" data-ownership-notice="{{ $ownership }}">
                O CPF/CNPJ do proprietário no CRLV-e não é o da sua conta. Ao continuar, o veículo entra em consignação: no próximo passo você envia a procuração do proprietário, e a equipe RevisaLog analisa.
            </x-ui.alert>
    @endswitch
@endif
