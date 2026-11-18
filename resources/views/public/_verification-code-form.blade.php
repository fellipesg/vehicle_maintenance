{{--
    Campo de código da conferência pública, usado em /verificar e no 404 de /v/{código} e de
    /laudo/{código}. Envia GET para /verificar, que normaliza o código (minúsculas, sem hífens, sem
    prefixo) e leva à página do documento: o Selo da oficina (RVL) em /v/{código} ou o laudo de
    vistoria cautelar (LDO) em /laudo/{código}.

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
        hint="Fica no relatório em PDF ou no laudo da vistoria, ao lado do QR code: RVL-XXXX-XX para o selo, LDO-XXXX-XX para o laudo."
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
    <x-ui.button type="submit" icon="shield-check" loading-label="Conferindo…">Conferir código</x-ui.button>
</form>
