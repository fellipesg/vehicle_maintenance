{{--
    Rótulo de controle de formulário. O <x-ui.field> já desenha um; use este sozinho só em
    composições próprias.

    Props:
    - for: id do controle.
    - required: asterisco visual (aria-hidden) + "(obrigatório)" só para leitor de tela.
    - optional: "(opcional)" visível, para formulários em que quase tudo é obrigatório.
    - srOnly: rótulo só para leitor de tela (busca, linha de tabela).

    Ex.: <x-ui.label for="plate" required>Placa</x-ui.label>
--}}@props([
    'for' => null,
    'required' => false,
    'optional' => false,
    'srOnly' => false,
])
<label {{ $attributes->class([
    'block text-sm font-medium text-foreground',
    'sr-only' => $srOnly,
])->merge(['for' => $for, 'data-slot' => 'label']) }}>{{ $slot }}@if($required)<span class="ml-0.5 text-danger" aria-hidden="true">*</span><span class="sr-only"> (obrigatório)</span>@elseif($optional) <span class="font-normal text-muted-foreground">(opcional)</span>@endif</label>
