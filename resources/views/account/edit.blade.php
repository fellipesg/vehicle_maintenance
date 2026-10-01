@extends('layouts.app')

@section('title', 'Minha conta')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Minha conta"
            description="Seus dados de acesso, a senha e a exclusão da conta."
            :breadcrumbs="[['Início', $homeUrl], ['Minha conta']]"
        >
            <x-ui.badge icon="user-circle">Conta de {{ $accountLabel }}</x-ui.badge>
        </x-ui.page-header>

        <div class="space-y-6">
            <x-ui.card as="section" id="dados" title="Dados pessoais" description="Você entra com este e-mail, e é para ele que enviamos avisos e o link de redefinição de senha." heading-level="h2">
                <form method="POST" action="{{ route('account.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-ui.form-errors id="dados-erros" :ids="['current_password' => 'dados_current_password']" />

                    <x-ui.field name="name" label="Nome" required>
                        <x-ui.input :value="$user->name" required maxlength="255" autocomplete="name" />
                    </x-ui.field>

                    <x-ui.field name="email" label="E-mail" required>
                        <x-ui.input type="email" :value="$user->email" required maxlength="255" autocomplete="email" inputmode="email" />
                    </x-ui.field>

                    {{-- Id próprio: o cartão Senha também tem um campo current_password. --}}
                    <x-ui.field name="current_password" label="Senha atual" hint="Obrigatória só para trocar o e-mail.">
                        <x-ui.input type="password" id="dados_current_password" autocomplete="current-password" />
                    </x-ui.field>

                    {{-- A dica é trocada por resources/js/form-ux.js enquanto a pessoa digita (data-field-hint). --}}
                    <x-ui.field name="phone" label="Telefone" optional>
                        <x-ui.input type="tel" :value="$user->phone" data-mask="digits" data-max-digits="11" data-min-digits="10" maxlength="11" inputmode="numeric" placeholder="11999999999" aria-describedby="phone-hint" />
                        <p id="phone-hint" class="text-sm text-muted-foreground" data-field-hint data-default-hint="Somente números, com DDD" data-idle-class="text-sm text-muted-foreground" data-ok-class="text-sm text-success" data-error-class="text-sm text-danger">Somente números, com DDD</p>
                    </x-ui.field>

                    <div class="flex justify-end max-sm:*:grow">
                        <x-ui.button type="submit" loading-label="Salvando…">Salvar alterações</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card as="section" id="senha" title="Senha" description="Depois da troca, os outros aparelhos saem da conta; este continua conectado." heading-level="h2">
                <form method="POST" action="{{ route('account.password') }}" class="space-y-4" data-password-form>
                    @csrf
                    @method('PUT')

                    <x-ui.form-errors bag="updatePassword" :threshold="1" id="senha-erros" />

                    <x-ui.field name="current_password" label="Senha atual" bag="updatePassword" required>
                        <x-ui.input type="password" required autocomplete="current-password" />
                    </x-ui.field>

                    <div class="space-y-2">
                        <x-ui.field name="password" label="Senha nova" bag="updatePassword" required>
                            <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-field aria-describedby="password-criteria" />
                        </x-ui.field>
                        @include('auth.partials.password-criteria')
                    </div>

                    <x-ui.field name="password_confirmation" label="Confirmar senha nova" bag="updatePassword" required>
                        <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-confirmation />
                    </x-ui.field>

                    <div class="flex justify-end max-sm:*:grow">
                        <x-ui.button type="submit" icon="key" loading-label="Salvando…">Trocar senha</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card as="section" id="excluir-conta" title="Excluir conta" heading-level="h2" class="border-danger/40">
                <x-slot:description>
                    A exclusão não pode ser desfeita.
                </x-slot:description>

                <div class="space-y-2 text-sm text-muted-foreground">
                    <p>Ao excluir a conta:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>seu nome, e-mail, telefone, documento e endereço são apagados, e você perde o acesso;</li>
                        <li>os veículos saem da sua conta;</li>
                        <li>as manutenções continuam no histórico de cada veículo, ligadas ao chassi, para os próximos donos.</li>
                        @if($user->isWorkshop())
                            <li>o perfil da oficina e as ordens de serviço continuam, porque sustentam o Selo da oficina das manutenções já registradas. Para tirar a oficina do diretório, <x-ui.link :href="route('contact.show', ['assunto' => 'support'])" variant="inline">fale com a gente</x-ui.link>.</li>
                        @endif
                    </ul>
                </div>

                <form
                    method="POST"
                    action="{{ route('account.destroy') }}"
                    class="mt-4 space-y-4"
                    data-confirm="Seus dados pessoais serão apagados e você sairá da conta. O histórico dos veículos continua ligado ao chassi. Não é possível desfazer."
                    data-confirm-title="Excluir sua conta?"
                    data-confirm-action-label="Excluir conta"
                    data-confirm-variant="danger"
                >
                    @csrf
                    @method('DELETE')

                    <x-ui.form-errors bag="deleteAccount" :threshold="1" id="exclusao-erros" :ids="['password' => 'delete_account_password']" />

                    <x-ui.field name="password" label="Sua senha" hint="Para confirmar que é você." bag="deleteAccount">
                        <x-ui.input type="password" id="delete_account_password" required autocomplete="current-password" />
                    </x-ui.field>

                    <div class="flex justify-end max-sm:*:grow">
                        <x-ui.button type="submit" variant="danger" icon="trash">Excluir minha conta</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </x-ui.container>
@endsection
