{{--
    Imagem recortada na proporção fixa antes do envio (capas do veículo: paisagem 16:9 e retrato
    9:16, .ai/rules/user-vehicles.md). Sem JS é um <input type="file"> comum, com a imagem atual (se
    houver) e a regra de tipos. Com resources/js/ui/image-cropper.js (initImageCroppers):
    - sem imagem, a área de soltar abre o seletor (Tab e Espaço no input, que fica visualmente oculto);
    - escolhida ou solta a foto, abre o palco com a moldura na proporção: arrastar (mouse e toque) ou
      setas posicionam; zoom pelo controle deslizante, pelos botões − e +, pela roda e pela pinça;
    - "Usar este recorte" gera um JPEG (qualidade 0,85, lado maior até 1920 px) que substitui o
      arquivo do input; "Cancelar" volta ao que valia antes (a imagem atual continua);
    - a prévia mostra o resultado inteiro (object-contain), com "Ajustar recorte", "Trocar imagem" e
      "Descartar". Enviar o formulário com o palco aberto recorta o enquadramento atual e segue.
    O arquivo original nunca sobe quando há JS. O servidor continua validando tipo e tamanho.

    Tem rótulo, dica e erro próprios: não vai dentro de <x-ui.field>.

    Props:
    - name (obrigatório): name do input. O id sai dele (cover_portrait).
    - label (obrigatório): rótulo visível. Também dá contexto aos botões e ao palco para o leitor de
      tela ("Trocar imagem, Capa retrato (celular em pé)").
    - aspect: 16:9 (o padrão) | 9:16.
    - current: URL da imagem que já existe. Aparece inteira até ser trocada.
    - currentAlt: alt dessa imagem; o padrão é "{currentLabel}: {label}".
    - currentLabel: legenda da imagem atual; o padrão é "Imagem atual".
    - hint: dica abaixo do rótulo, ligada ao input.
    - accept: tipos aceitos; o padrão é 'image/jpeg,image/png,image/webp'.
    - maxMb: limite do arquivo enviado, o mesmo do max: da validação dividido por 1024. Entra na regra
      sem JS; com JS, o JPEG recortado é conferido contra ele (a foto escolhida pode ser maior).
    - maxSide: lado maior do JPEG, em px (1920). quality: qualidade do JPEG (0.85).
    - required: asterisco no rótulo e required no input enquanto não há imagem atual.
    - optional: "(opcional)" no rótulo.
    - bag / error / invalid: como no <x-ui.input>.

    Os demais atributos (disabled, data-*) vão para o <input>; class vai para a moldura externa.

    Ex.:
    <x-ui.image-cropper name="cover" aspect="16:9" label="Capa paisagem (celular deitado)"
        :current="$vehicle->cover_photo_url" current-label="Capa atual" :max-mb="5" optional />
    <x-ui.image-cropper name="cover_portrait" aspect="9:16" label="Capa retrato (celular em pé)"
        hint="Usada em telas estreitas, avatares e no PDF." :max-mb="5" optional />
--}}@props([
    'name' => null,
    'label' => null,
    'aspect' => '16:9',
    'id' => null,
    'current' => null,
    'currentAlt' => null,
    'currentLabel' => 'Imagem atual',
    'hint' => null,
    'accept' => 'image/jpeg,image/png,image/webp',
    'maxMb' => null,
    'maxSide' => 1920,
    'quality' => 0.85,
    'required' => false,
    'optional' => false,
    'bag' => null,
    'error' => null,
    'invalid' => false,
])
@php
    \App\Support\UiProps::required('x-ui.image-cropper', 'name', $name, 'O name identifica o arquivo no envio.');
    \App\Support\UiProps::required('x-ui.image-cropper', 'label', $label, 'O rótulo nomeia o campo, o palco e os botões para o leitor de tela.');

    $cropperAspect = \App\Support\UiProps::oneOf('x-ui.image-cropper', 'aspect', $aspect, ['16:9', '9:16'], '16:9');
    $cropperIsPortrait = $cropperAspect === '9:16';
    $cropperId = $id ?? \App\Support\FormField::controlId($name) ?? 'imagem-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $cropperLabel = (string) $label;
    $cropperCurrent = filled($current) ? (string) $current : null;
    $cropperCurrentLabel = filled($currentLabel) ? (string) $currentLabel : 'Imagem atual';
    $cropperCurrentAlt = filled($currentAlt) ? (string) $currentAlt : $cropperCurrentLabel.': '.$cropperLabel;
    $cropperError = filled($error) ? (string) $error : \App\Support\FormField::error($errors ?? null, $name, $bag);
    $cropperInvalid = (bool) $invalid || $cropperError !== null;
    $cropperMaxMb = filled($maxMb) ? (float) $maxMb : null;
    $cropperMaxSide = max(320, (int) $maxSide);
    $cropperQuality = min(1, max(0.1, (float) $quality));
    $cropperTypes = \App\Support\FormField::acceptLabel($accept);
    $cropperFallbackRules = implode(' · ', array_filter([
        \App\Support\FormField::fileRules($accept, $cropperMaxMb),
        'proporção '.$cropperAspect,
    ]));
    $cropperEnhancedRules = implode(' · ', array_filter([
        $cropperTypes,
        'você enquadra em '.$cropperAspect.' antes do envio',
    ]));
    $cropperHasHint = filled($hint);
    $cropperDescribedBy = \App\Support\FormField::describedBy(
        $cropperHasHint ? $cropperId.'-hint' : null,
        $cropperId.'-rules',
        $cropperError !== null ? $cropperId.'-error' : null,
    );
    $cropperFrameClass = $cropperIsPortrait ? 'aspect-[9/16] w-full max-w-44' : 'aspect-video w-full max-w-xl';
    $cropperStageClass = $cropperIsPortrait ? 'aspect-[4/5] w-full max-w-sm' : 'aspect-[3/2] w-full max-w-xl';
@endphp
<div {{ $attributes->only('class')->class(['group/image-cropper grid min-w-0 content-start gap-2']) }}
     role="group"
     aria-labelledby="{{ $cropperId }}-label"
     data-slot="image-cropper"
     data-image-cropper
     data-state="{{ $cropperCurrent !== null ? 'current' : 'empty' }}"
     data-aspect="{{ $cropperAspect }}"
     data-max-side="{{ $cropperMaxSide }}"
     data-quality="{{ $cropperQuality }}"
     data-label="{{ $cropperLabel }}"
     @if($cropperMaxMb !== null) data-max-bytes="{{ (int) round($cropperMaxMb * 1024 * 1024) }}" data-max-label="{{ \App\Support\FormField::megabytes($cropperMaxMb) }}" @endif
     @if($cropperTypes !== null) data-types-label="{{ $cropperTypes }}" @endif>
    <x-ui.label :for="$cropperId" id="{{ $cropperId }}-label" :required="$required" :optional="$optional">{{ $cropperLabel }}</x-ui.label>
    @if($cropperHasHint)
        <p id="{{ $cropperId }}-hint" data-slot="field-hint" class="text-sm text-muted-foreground">{{ $hint }}</p>
    @endif

    <figure data-image-cropper-preview class="grid justify-items-start gap-1.5" @if($cropperCurrent === null) hidden @endif>
        <div class="relative overflow-hidden rounded-card border border-border bg-surface-muted {{ $cropperFrameClass }} group-data-dragging/image-cropper:border-ring group-data-dragging/image-cropper:ring-2 group-data-dragging/image-cropper:ring-ring/30">
            <img data-image-cropper-preview-image @if($cropperCurrent !== null) src="{{ $cropperCurrent }}" @endif alt="{{ $cropperCurrentAlt }}" class="absolute inset-0 size-full object-contain object-center">
        </div>
        <figcaption data-image-cropper-preview-caption class="text-xs text-muted-foreground">{{ $cropperCurrentLabel }}</figcaption>
    </figure>

    <input {{ $attributes->except('class')->class([
        'peer block w-full cursor-pointer rounded-control border border-input bg-surface text-sm text-muted-foreground shadow-sm',
        'file:mr-3 file:h-10 file:cursor-pointer file:border-0 file:border-r file:border-solid file:border-input file:bg-surface-muted file:px-4 file:text-sm file:font-semibold file:text-foreground hover:file:bg-border',
        'focus:outline-hidden focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30',
        'aria-invalid:border-danger disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-60',
        'group-data-enhanced/image-cropper:sr-only',
    ])->merge([
        'type' => 'file',
        'name' => $name,
        'id' => $cropperId,
        'accept' => $accept,
        'required' => (bool) $required && $cropperCurrent === null,
        'aria-invalid' => $cropperInvalid ? 'true' : null,
        'aria-describedby' => $cropperDescribedBy,
        'data-slot' => 'control',
        'data-image-cropper-input' => true,
    ]) }}>

    <label for="{{ $cropperId }}" data-image-cropper-dropzone hidden class="flex min-h-32 max-w-xl cursor-pointer flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-input bg-surface px-6 py-6 text-center transition-colors duration-fast ease-smooth-out motion-reduce:transition-none hover:border-ring peer-focus-visible:border-ring peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring peer-aria-invalid:border-danger peer-disabled:cursor-not-allowed peer-disabled:opacity-60 group-data-dragging/image-cropper:border-ring group-data-dragging/image-cropper:bg-accent">
        <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-accent text-accent-foreground">
            <x-ui.icon name="photo" />
        </span>
        <span aria-hidden="true" class="text-sm text-muted-foreground">
            <span class="font-semibold text-link underline underline-offset-2">Escolher imagem</span>
            ou arraste até aqui
        </span>
    </label>

    <div data-image-cropper-actions hidden class="flex flex-wrap gap-2">
        <x-ui.button variant="secondary" size="sm" icon="pencil-square" data-image-cropper-adjust hidden>Ajustar recorte<span class="sr-only">, {{ $cropperLabel }}</span></x-ui.button>
        <x-ui.button variant="secondary" size="sm" icon="arrow-path" data-image-cropper-change>Trocar imagem<span class="sr-only">, {{ $cropperLabel }}</span></x-ui.button>
        <x-ui.button variant="ghost" size="sm" icon="x-mark" data-image-cropper-discard hidden>Descartar<span class="sr-only"> o novo recorte, {{ $cropperLabel }}</span></x-ui.button>
    </div>

    <div data-image-cropper-editor hidden class="grid max-w-xl gap-3 rounded-card border border-border bg-surface p-3 shadow-sm sm:p-4">
        <p id="{{ $cropperId }}-instructions" class="text-sm text-muted-foreground">Arraste a imagem para enquadrar e use o zoom para aproximar. Só o que fica dentro da moldura vai aparecer.</p>
        <div data-image-cropper-stage
             tabindex="0"
             role="application"
             aria-roledescription="área de recorte"
             aria-label="Enquadramento: {{ $cropperLabel }}"
             aria-describedby="{{ $cropperId }}-instructions {{ $cropperId }}-keys"
             class="relative isolate cursor-grab touch-none overflow-hidden rounded-control select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring data-panning:cursor-grabbing {{ $cropperStageClass }}">
            <div aria-hidden="true" class="theme-inverse absolute inset-0 bg-background">
                <img data-image-cropper-image alt="" draggable="false" class="pointer-events-none absolute top-0 left-0 max-w-none origin-top-left select-none will-change-transform">
                <div data-image-cropper-frame class="pointer-events-none absolute rounded-sm outline-2 outline-foreground shadow-[0_0_0_9999px_var(--color-overlay)]">
                    <span class="absolute inset-y-0 left-1/3 w-px bg-foreground/40"></span>
                    <span class="absolute inset-y-0 left-2/3 w-px bg-foreground/40"></span>
                    <span class="absolute inset-x-0 top-1/3 h-px bg-foreground/40"></span>
                    <span class="absolute inset-x-0 top-2/3 h-px bg-foreground/40"></span>
                </div>
            </div>
        </div>
        <p id="{{ $cropperId }}-keys" class="text-xs text-muted-foreground">No teclado, com a imagem em foco: setas movem (com Shift, mais longe), + e − mudam o zoom, 0 volta ao início e Enter usa o recorte.</p>
        <div class="flex items-center gap-2">
            <label for="{{ $cropperId }}-zoom" class="text-sm font-medium text-foreground">Zoom</label>
            <x-ui.icon-button icon="minus" label="Diminuir zoom" size="sm" data-image-cropper-zoom-out />
            <input type="range" id="{{ $cropperId }}-zoom" min="100" max="400" step="5" value="100" aria-valuetext="100%" data-image-cropper-zoom class="h-10 min-w-0 flex-1 cursor-pointer accent-ring">
            <x-ui.icon-button icon="plus" label="Aumentar zoom" size="sm" data-image-cropper-zoom-in />
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" icon="check" loading-label="Recortando…" data-image-cropper-apply>Usar este recorte</x-ui.button>
            <x-ui.button variant="ghost" data-image-cropper-cancel>Cancelar</x-ui.button>
        </div>
    </div>

    <p id="{{ $cropperId }}-rules" class="text-xs text-muted-foreground">
        <span class="group-data-enhanced/image-cropper:hidden">{{ $cropperFallbackRules }}</span>
        <span class="hidden group-data-enhanced/image-cropper:inline">{{ $cropperEnhancedRules }}</span>
    </p>
    <p id="{{ $cropperId }}-feedback" data-image-cropper-feedback role="alert" class="flex items-start gap-1.5 text-sm text-danger empty:hidden"></p>
    @if($cropperError !== null)
        <p id="{{ $cropperId }}-error" data-slot="field-error" class="flex items-start gap-1.5 text-sm text-danger">
            <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
            <span><span class="sr-only">Erro: </span>{{ $cropperError }}</span>
        </p>
    @endif
    <p data-image-cropper-status role="status" aria-live="polite" class="sr-only"></p>
</div>
