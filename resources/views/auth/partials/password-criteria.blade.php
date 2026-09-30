{{--
    Critérios da senha nova, marcados enquanto a pessoa digita por resources/js/form-ux.js
    (form[data-password-form] com [data-password-field] e [data-password-confirmation]). Sem
    JavaScript a lista fica como dica estática. O campo da senha nova aponta para ela com
    aria-describedby="{{ $criteriaId ?? 'password-criteria' }}". As cores vêm dos papéis
    semânticos, então servem ao card escuro do guest e ao fundo claro de "Minha conta".

    O glifo ✓/○ é aria-hidden; o estado vai em texto ([data-rule-status], "atendido" ou "pendente").
    A lista é aria-live e cada item é aria-atomic: quando um critério muda, o leitor de tela lê o
    item inteiro ("Mínimo de 8 caracteres, atendido"), e não a cada tecla.
--}}
<ul id="{{ $criteriaId ?? 'password-criteria' }}"
    class="space-y-1 text-sm text-muted-foreground"
    data-password-criteria
    data-ok-class="text-success"
    data-idle-class="text-muted-foreground"
    data-error-class="text-danger"
    aria-live="polite">
    <li data-rule="length" class="flex items-center gap-2" aria-atomic="true">
        <span data-rule-icon aria-hidden="true">○</span>
        <span>Mínimo de 8 caracteres<span class="sr-only" data-rule-status>, pendente</span></span>
    </li>
    <li data-rule="match" class="flex items-center gap-2" aria-atomic="true">
        <span data-rule-icon aria-hidden="true">○</span>
        <span>Confirmação igual à senha<span class="sr-only" data-rule-status>, pendente</span></span>
    </li>
</ul>
