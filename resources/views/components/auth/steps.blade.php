{{--
    Trilha das 3 etapas do cadastro (/cadastro): onde a pessoa está e o que falta.

    A etapa atual não é dita só pela cor: ela leva o texto "Etapa N de 3" e aria-current.

    Props:
    - current: 1, 2 ou 3.
--}}
@props(['current' => 1])
@php
    $steps = ['Conta', 'Senha', 'Dados'];
@endphp
<nav {{ $attributes->merge(['data-slot' => 'auth-steps']) }} aria-label="Etapas do cadastro">
    <p class="text-xs font-medium text-muted-foreground">Etapa {{ $current }} de {{ count($steps) }}</p>
    <ol role="list" class="mt-2 flex items-center gap-2">
        @foreach($steps as $index => $label)
            @php($number = $index + 1)
            <li @class(['flex min-w-0 flex-1 items-center gap-2'])
                @if($number === $current) aria-current="step" @endif>
                <span @class([
                    'inline-flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                    'border-primary bg-primary text-primary-foreground' => $number <= $current,
                    'border-border-strong text-muted-foreground' => $number > $current,
                ])>{{ $number }}</span>
                <span @class([
                    'truncate text-xs',
                    'font-semibold text-foreground' => $number === $current,
                    'text-muted-foreground' => $number !== $current,
                ])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
