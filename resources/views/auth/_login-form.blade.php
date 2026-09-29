{{--
    Formulário de entrada dos 4 portais (auth.login com /login/usuario, /login/lojista,
    /login/oficina e /login/admin).

    Variáveis: $submitRoute (URL do POST) e $portal (admin, lojista, usuario ou oficina), que leva o
    "Esqueci minha senha" e a volta de lá ao mesmo portal.

    Erros: 'credentials' (e-mail ou senha incorretos) aparece no topo, sem marcar os campos; 'email'
    e 'password' ficam no próprio campo. session('suggested_portal') aponta o portal certo para
    quem entrou pela porta errada. Com url.intended (link protegido aberto antes de entrar), um
    aviso explica que a pessoa volta para onde estava.
--}}
<form method="POST" action="{{ $submitRoute }}" class="space-y-4">
    @csrf

    @if(session()->has('url.intended') && ! $errors->any())
        <x-ui.alert variant="info" icon="lock-closed">Entre para continuar de onde parou.</x-ui.alert>
    @endif

    @error('credentials')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <div class="space-y-2">
        <x-ui.field name="email" label="E-mail">
            <x-ui.input type="email" required autofocus autocomplete="email" inputmode="email" />
        </x-ui.field>
        @if($suggestedPortal = session('suggested_portal'))
            <p class="text-sm">
                <x-ui.link :href="$suggestedPortal['url']" arrow>{{ $suggestedPortal['label'] }}</x-ui.link>
            </p>
        @endif
    </div>

    <x-ui.field name="password" label="Senha">
        <x-slot:aside>
            <x-ui.link :href="route('password.request', ['portal' => $portal ?? null])" class="inline-flex min-h-10 items-center">Esqueci minha senha</x-ui.link>
        </x-slot:aside>
        <x-ui.input type="password" required autocomplete="current-password" />
    </x-ui.field>

    <x-ui.checkbox name="remember" label="Lembrar de mim neste aparelho" />

    <x-ui.button type="submit" size="lg" full loading-label="Entrando…">Entrar</x-ui.button>
</form>
