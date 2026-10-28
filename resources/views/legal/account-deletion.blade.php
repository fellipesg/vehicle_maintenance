@extends('layouts.app')

@section('title', 'Excluir conta e dados')

@push('head')
    <meta name="description" content="Como excluir sua conta RevisaLog e seus dados pessoais, pelo aplicativo ou por este formulário, e o que acontece com o histórico do veículo.">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header eyebrow="RevisaLog" title="Excluir conta e dados">
        <x-slot:description>
            Esta é a página de exclusão de conta do RevisaLog (revisalog.com.br). Aqui você pede a exclusão
            da sua conta e dos dados pessoais ligados a ela, com ou sem o aplicativo instalado.
        </x-slot:description>
    </x-ui.page-header>

    <div class="grid gap-6">
        <x-ui.card padding="lg">
            <x-ui.section title="Como pedir a exclusão">
                <div class="grid gap-5">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Pelo aplicativo</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Abra o RevisaLog e vá em <strong>Perfil</strong> &rsaquo; <strong>Configurações</strong>
                            &rsaquo; <strong>Excluir conta</strong>. A exclusão acontece na hora, depois da confirmação.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-foreground">Sem o aplicativo</h3>
                        <ol class="mt-1 grid gap-1 text-sm text-muted-foreground">
                            <li>1. Informe abaixo o e-mail da sua conta.</li>
                            <li>2. Enviamos um link de confirmação para esse endereço. Ele vale por 1 hora.</li>
                            <li>3. Abra o link e confirme. Só então a conta é excluída.</li>
                        </ol>
                        <p class="mt-2 text-sm text-muted-foreground">
                            O link vai para o e-mail cadastrado, nunca para outro endereço: é assim que confirmamos
                            que o pedido é mesmo seu.
                        </p>
                    </div>
                </div>
            </x-ui.section>
        </x-ui.card>

        <x-ui.card padding="lg">
            <form method="POST" action="{{ route('account-deletion.request') }}" class="relative grid gap-5">
                @csrf

                <x-ui.form-errors />

                {{-- Armadilha para robôs: fora da tela, sem foco e escondida do leitor de tela. --}}
                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                </div>

                <x-ui.field name="email" label="E-mail da conta" hint="O link de confirmação chega neste endereço." required>
                    <x-ui.input type="email" :value="auth()->user()?->email" required maxlength="255" autocomplete="email" inputmode="email" />
                </x-ui.field>

                <div class="flex justify-end border-t border-border pt-5 max-sm:*:grow" data-slot="form-actions">
                    <x-ui.button type="submit" icon="envelope" loading-label="Enviando…">Enviar link de confirmação</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card padding="lg">
            <x-ui.section title="O que é apagado">
                <ul class="grid gap-1.5 text-sm text-muted-foreground">
                    <li>Nome, e-mail, telefone e documento (CPF ou CNPJ).</li>
                    <li>Endereço completo: CEP, logradouro, número, complemento, cidade, estado e coordenadas.</li>
                    <li>Foto de perfil, removida do armazenamento.</li>
                    <li>Senha, sessões e tokens de acesso.</li>
                    <li>Dispositivos registrados para receber notificações.</li>
                    <li>Autenticação em duas etapas: segredo e códigos de recuperação.</li>
                    <li>Ligação da conta com o login do Google ou da Apple.</li>
                    <li>O vínculo entre você e os veículos da sua conta.</li>
                    <li>As notificações que você recebeu na plataforma.</li>
                    <li>Os relatórios em PDF que você gerou, registro e arquivo.</li>
                </ul>
            </x-ui.section>
        </x-ui.card>

        <x-ui.card padding="lg">
            <x-ui.section title="O que permanece">
                <div class="grid gap-3 text-sm text-muted-foreground">
                    <p>
                        <strong class="text-foreground">O histórico do veículo continua.</strong>
                        Veículos, histórico de placas e manutenções ficam ligados ao chassi, não à sua conta — é o
                        que o próximo dono e qualquer interessado consultam. Depois da exclusão, esses registros
                        deixam de ter qualquer ligação com você: não carregam mais seu nome, e-mail nem contato.
                    </p>
                    <p>
                        Para que esse histórico não desapareça, o cadastro em si não é removido da base: ele fica
                        como <strong class="text-foreground">&ldquo;Conta excluída&rdquo;</strong>, sem nenhum dado
                        pessoal e sem possibilidade de login. Apagar a linha levaria junto as manutenções que você
                        registrou, inclusive as de carros que hoje pertencem a outras pessoas.
                    </p>
                    <p>
                        <strong class="text-foreground">Notas fiscais e fotos das manutenções também ficam</strong>,
                        mesmo as que você anexou. Elas são prova do serviço feito naquele chassi, não cadastro de
                        quem o declarou, e sem elas o histórico perde o valor para o próximo dono. Se algum
                        documento ou foto expuser um dado pessoal seu que não deveria continuar ali, peça a remoção
                        pelo suporte e analisamos caso a caso.
                    </p>
                    <p>
                        A exclusão é imediata e definitiva: não guardamos cópia dos dados pessoais apagados nem há
                        como desfazer o pedido. Para criar uma conta nova depois, é preciso começar do zero.
                    </p>
                </div>
            </x-ui.section>
        </x-ui.card>

        <p class="text-sm text-muted-foreground">
            Dúvidas sobre seus dados? Escreva para
            <x-ui.link :href="'mailto:'.$supportEmail" variant="inline">{{ $supportEmail }}</x-ui.link>
            ou leia a <x-ui.link :href="route('legal.privacy')" variant="inline">Política de privacidade</x-ui.link>.
        </p>
    </div>
</x-ui.container>
@endsection
