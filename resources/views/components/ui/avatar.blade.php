{{--
    Avatar de pessoa ou oficina: imagem opcional sobre as iniciais do nome.

    Props:
    - name: nome da pessoa ou oficina. Obrigatório; gera as iniciais (primeira letra do primeiro e do
      último nome, ignorando "de", "da", "do", "dos", "das" e "e": "Oficina do Zé" = OZ).
    - src: URL da imagem (foto ou logo). Fica por cima das iniciais: se não carregar, as iniciais
      aparecem. Sempre object-contain sobre fundo branco, porque logo de oficina costuma ser retrato
      e não pode ser cortado (.ai/rules/pdfs.md).
    - size: xs (24px) | sm (32px) | md (40px, o padrão) | lg (48px) | xl (64px).
    - shape: circle (o padrão) | square (cantos rounded-control, bom para logo).
    - decorative: true (o padrão) quando o nome já aparece ao lado; o avatar fica aria-hidden. Com
      false, vira imagem com nome (role="img" e aria-label = name).

    Não substitui o .prov-marker da procedência.

    Ex.: <x-ui.avatar :name="$user->name" size="sm" />
         <x-ui.avatar :name="$workshop->name" :src="$workshop->logoUrl()" shape="square" :decorative="false" />
--}}
@props([
    'name' => null,
    'src' => null,
    'size' => 'md',
    'shape' => 'circle',
    'decorative' => true,
])
@php
    \App\Support\UiProps::required('x-ui.avatar', 'name', $name);

    $avatarSize = \App\Support\UiProps::oneOf('x-ui.avatar', 'size', $size, ['xs', 'sm', 'md', 'lg', 'xl'], 'md');
    $avatarShape = \App\Support\UiProps::oneOf('x-ui.avatar', 'shape', $shape, ['circle', 'square'], 'circle');
    $avatarName = trim((string) $name);
    $avatarIsDecorative = (bool) $decorative;

    $avatarWords = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $avatarName)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $avatarMeaningfulWords = array_values(array_filter(
        $avatarWords,
        fn (string $word): bool => ! in_array(mb_strtolower($word), ['de', 'da', 'do', 'das', 'dos', 'e'], true),
    )) ?: $avatarWords;
    $avatarInitials = match (count($avatarMeaningfulWords)) {
        0 => '',
        1 => mb_strtoupper(mb_substr($avatarMeaningfulWords[0], 0, 1)),
        default => mb_strtoupper(mb_substr($avatarMeaningfulWords[0], 0, 1).mb_substr($avatarMeaningfulWords[array_key_last($avatarMeaningfulWords)], 0, 1)),
    };

    $avatarSizeClasses = match ($avatarSize) {
        'xs' => 'size-6 text-xs',
        'sm' => 'size-8 text-xs',
        'md' => 'size-10 text-sm',
        'lg' => 'size-12 text-base',
        'xl' => 'size-16 text-lg',
    };
    $avatarShapeClass = $avatarShape === 'circle' ? 'rounded-full' : 'rounded-control';
    // Com imagem o fundo é branco nos dois escopos: logo de oficina é desenhado para fundo claro. Se
    // a imagem não carregar, as iniciais aparecem sobre o branco (accent-foreground: 6,31:1).
    $avatarSurfaceClass = filled($src) ? 'bg-white ring-1 ring-border' : 'bg-accent';
@endphp
<span {{ $attributes->class([
    'relative inline-flex shrink-0 items-center justify-center overflow-hidden font-semibold text-accent-foreground select-none',
    $avatarSurfaceClass,
    $avatarSizeClasses,
    $avatarShapeClass,
])->merge([
    'data-slot' => 'avatar',
    'role' => $avatarIsDecorative ? null : 'img',
    'aria-label' => $avatarIsDecorative ? null : $avatarName,
    'aria-hidden' => $avatarIsDecorative ? 'true' : null,
]) }}>
    @if($avatarInitials !== '')
        <span data-slot="avatar-initials" aria-hidden="true">{{ $avatarInitials }}</span>
    @else
        <x-ui.icon name="user" class="size-3/5" />
    @endif
    @if(filled($src))
        <img src="{{ $src }}" alt="" loading="lazy" decoding="async" data-slot="avatar-image" @class(['absolute inset-0 size-full object-contain p-0.5', $avatarShapeClass])>
    @endif
</span>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp
