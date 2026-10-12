{{--
    Minha loja: cadastro da garagem (CNPJ, contato e endereço) em uma página só — a loja não tem
    perfil público como a oficina, então não existe o par ver/editar.

    O aviso do topo aparece enquanto falta o CNPJ, porque é ele que separa estoque próprio de
    consignação no assistente de veículo (App\Support\Vehicle\VehicleEntryFlow::ownershipFor).

    O CEP tem data-cep-autofill: com os 8 dígitos, resources/js/workshop-address-cep.js busca no
    ViaCEP e preenche rua, bairro, cidade e UF pelo name dos campos deste formulário.

    Variáveis: $garage e $stateOptions.
--}}
@extends('layouts.app')

@section('title', 'Minha loja')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Minha loja"
            description="Os dados da sua garagem. O CNPJ é o que permite registrar um veículo como estoque próprio."
            :breadcrumbs="$garage->isApproved() ? [['Início', route('garage.dashboard')], ['Minha loja']] : [['Minha loja']]"
        >
            <x-ui.badge :variant="$garage->status->badgeVariant()">{{ $garage->status->label() }}</x-ui.badge>
        </x-ui.page-header>

        @if($garage->status === \App\Enums\GarageStatus::Pending)
            <x-ui.alert variant="warning" role="status" title="Loja em análise" class="mb-6" data-garage-pending>
                Estamos conferindo o CNPJ da sua loja. Enquanto isso, só esta tela fica disponível — estoque, manutenções e consignação abrem assim que a análise terminar. Complete os dados abaixo para agilizar.
            </x-ui.alert>
        @elseif($garage->status === \App\Enums\GarageStatus::Rejected)
            <x-ui.alert variant="danger" role="status" title="Cadastro recusado" class="mb-6" data-garage-rejected>
                @if(filled($garage->review_notes))
                    {{ $garage->review_notes }}
                @else
                    Não conseguimos confirmar o CNPJ desta loja.
                @endif
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" :href="route('contact.show')">Falar com a equipe</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
        @elseif($garage->normalizedDocument() === null)
            <x-ui.alert variant="warning" role="status" title="Sua loja ainda não tem CNPJ" class="mb-6" data-garage-document-missing>
                Sem ele não conseguimos confirmar que um veículo é da loja, e todo CRLV-e entra como consignação, com procuração. Preencha o CNPJ abaixo.
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('garage.profile.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-ui.form-errors />

            <x-ui.form-section id="secao-identidade" title="Identidade" description="O CNPJ é comparado com o dono lido no CRLV-e para separar estoque próprio de consignação.">
                <x-ui.field name="name" label="Nome da loja" required>
                    <x-ui.input :value="$garage->name" required maxlength="255" autocomplete="organization" />
                </x-ui.field>

                <x-ui.field name="document" label="CNPJ" hint="14 dígitos, só números." optional>
                    <x-ui.input :value="$garage->document" maxlength="18" autocomplete="off" inputmode="numeric"
                                data-mask="digits" data-min-digits="14" data-max-digits="14"
                                placeholder="12345678000190" class="tabular-nums" />
                </x-ui.field>
            </x-ui.form-section>

            <x-ui.form-section id="secao-contato" title="Contato" description="Usado quando a equipe ou o proprietário de um veículo em consignação precisa falar com a loja.">
                <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
                    <x-ui.field name="phone" label="Telefone" hint="Com DDD, só números." optional>
                        <x-ui.input type="tel" :value="$garage->phone" autocomplete="tel-national" inputmode="numeric"
                                    data-mask="digits" data-min-digits="10" data-max-digits="11" placeholder="11999999999" />
                    </x-ui.field>
                    <x-ui.field name="email" label="E-mail" optional>
                        <x-ui.input type="email" :value="$garage->email" maxlength="255" autocomplete="email" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <x-ui.form-section id="secao-endereco" title="Endereço" description="Preencha o CEP e o resto do endereço vem junto.">
                <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
                    <x-ui.field name="cep" label="CEP" hint="8 dígitos." optional>
                        <x-ui.input :value="$garage->cep" autocomplete="postal-code" inputmode="numeric"
                                    data-mask="digits" data-min-digits="8" data-max-digits="8" data-cep-autofill
                                    placeholder="01310100" class="tabular-nums" />
                    </x-ui.field>
                    <x-ui.field name="street" label="Rua" optional class="sm:col-span-2">
                        <x-ui.input :value="$garage->street" maxlength="255" autocomplete="address-line1" />
                    </x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
                    <x-ui.field name="number" label="Número" optional>
                        <x-ui.input :value="$garage->number" maxlength="255" autocomplete="off" />
                    </x-ui.field>
                    <x-ui.field name="complement" label="Complemento" optional class="sm:col-span-2">
                        <x-ui.input :value="$garage->complement" maxlength="255" autocomplete="address-line2" />
                    </x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
                    <x-ui.field name="neighborhood" label="Bairro" optional>
                        <x-ui.input :value="$garage->neighborhood" maxlength="255" autocomplete="address-level3" />
                    </x-ui.field>
                    <x-ui.field name="city" label="Cidade" optional>
                        <x-ui.input :value="$garage->city" maxlength="255" autocomplete="address-level2" />
                    </x-ui.field>
                    <x-ui.field name="state" label="UF" optional>
                        <x-ui.select :options="$stateOptions" :value="$garage->state" placeholder="Selecione a UF" autocomplete="address-level1" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                @if($garage->isApproved())
                    <x-ui.button variant="secondary" :href="route('garage.dashboard')">Cancelar</x-ui.button>
                @endif
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar alterações</x-ui.button>
            </div>
        </form>
    </x-ui.container>
@endsection
