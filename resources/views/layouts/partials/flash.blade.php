{{--
    Mensagens de sessão (error, warning, success, status, info) dos três layouts, desenhadas por
    <x-ui.flash> (ícone, role status ou alert, prefixo para leitor de tela e "Fechar aviso").
    Variável opcional: $flashWrapperClass (contêiner externo). $flashTone não é mais necessário: as
    cores seguem os papéis semânticos e se ajustam dentro de .theme-inverse (guest). O botão
    "Fechar aviso" é tratado em resources/js/ui/flash.js.
--}}
<x-ui.flash :wrapper-class="$flashWrapperClass ?? 'mx-auto w-full max-w-7xl px-4 pt-4'" />
