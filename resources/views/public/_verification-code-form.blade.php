{{--
    Campo do código do Selo da oficina, usado em /verificar e no 404 de /v/{código}. Envia GET para
    /verificar, que normaliza o código (minúsculas, sem hífens, sem "RVL") e leva a /v/{código}.

    Variáveis:
    - $typedCode: valor inicial do campo (o código digitado antes).
    - $codeError: mensagem de erro do formato, ou null.
    - $autofocus: foca o campo ao abrir a página.
--}}
@php
    $verificationFormError = $codeError ?? null;
@endphp
<form method="GET" action="{{ route('verification.lookup') }}" class="space-y-4" data-verification-lookup-form>
    <x-ui.field
        name="codigo"
        label="Código de verificação"
        hint="Fica no relatório em PDF, ao lado do QR code, no formato RVL-XXXX-XX."
        :error="$verificationFormError"
        required
    >
        <x-ui.input
            :value="\Illuminate\Support\Str::limit((string) ($typedCode ?? ''), 40, '')"
            required
            maxlength="40"
            placeholder="RVL-XXXX-XX"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            enterkeyhint="go"
            class="font-mono tracking-wider uppercase"
            :autofocus="(bool) ($autofocus ?? false) || filled($verificationFormError)"
        />
    </x-ui.field>
    <x-ui.button type="submit" icon="shield-check" loading-label="Conferindo…">Conferir selo</x-ui.button>
</form>
