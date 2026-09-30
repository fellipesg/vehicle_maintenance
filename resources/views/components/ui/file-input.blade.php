{{--
    Envio de arquivo. Sem JS é um <input type="file"> nativo estilizado; com
    resources/js/ui/file-input.js (initFileInputs) vira uma área de soltar: o <label> tracejado
    abre o seletor, o input real fica visualmente oculto mas focável (Tab e Espaço funcionam e o
    foco aparece na área), e a lista mostra nome, tamanho e "Remover" de cada arquivo. O JS valida
    tipo, tamanho e quantidade em pt-BR antes do envio; o servidor continua validando.

    Props:
    - name: dentro de <x-ui.field> vem do campo por @aware. Com multiple, use "photos[]".
    - id: o padrão sai do name.
    - accept: tipos aceitos ('application/pdf,image/jpeg,image/png' ou 'image/*').
    - multiple: vários arquivos; o que se solta ou escolhe depois soma aos anteriores.
    - maxMb: tamanho máximo por arquivo, em MB (o mesmo do max: da validação, dividido por 1024).
    - maxFiles: quantidade máxima, com multiple.
    - rules: texto da regra abaixo da área. O padrão sai de accept, maxMb e maxFiles
      ("PDF, JPG ou PNG · até 10 MB"); false tira.
    - bag / invalid: como no <x-ui.input>.

    Use dentro de <x-ui.field>, que dá o rótulo, a dica e o erro do servidor. Os demais atributos
    (required, capture, data-*) vão para o <input>; class vai para a moldura externa.

    Ex.:
    <x-ui.field name="invoice" label="Nota fiscal">
        <x-ui.file-input accept="application/pdf,image/jpeg,image/png" :max-mb="10" />
    </x-ui.field>
--}}@aware([
    'name' => null,
    'bag' => null,
])
@props([
    'name' => null,
    'id' => null,
    'accept' => null,
    'multiple' => false,
    'maxMb' => null,
    'maxFiles' => null,
    'rules' => null,
    'bag' => null,
    'invalid' => false,
])
@php
    $fileId = $id ?? \App\Support\FormField::controlId($name) ?? 'arquivo-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $fileError = \App\Support\FormField::error($errors ?? null, $name, $bag);
    $fileInvalid = (bool) $invalid || $fileError !== null;
    $fileMaxMb = filled($maxMb) ? (float) $maxMb : null;
    $fileMaxFiles = $multiple && filled($maxFiles) ? max(1, (int) $maxFiles) : null;
    $fileRules = $rules === false ? null : (filled($rules) ? (string) $rules : \App\Support\FormField::fileRules($accept, $fileMaxMb, $fileMaxFiles));
    $fileTypesLabel = \App\Support\FormField::acceptLabel($accept);
@endphp
<div {{ $attributes->only('class')->class(['group/file-input grid min-w-0 gap-2']) }}
     data-slot="file-input"
     data-file-input
     @if($fileMaxMb !== null) data-max-bytes="{{ (int) round($fileMaxMb * 1024 * 1024) }}" data-max-label="{{ \App\Support\FormField::megabytes($fileMaxMb) }}" @endif
     @if($fileMaxFiles !== null) data-max-files="{{ $fileMaxFiles }}" @endif
     @if($fileTypesLabel !== null) data-types-label="{{ $fileTypesLabel }}" @endif>
    <input {{ $attributes->except('class')->class([
        'peer block w-full cursor-pointer rounded-control border border-input bg-surface text-sm text-muted-foreground shadow-sm',
        'file:mr-3 file:h-10 file:cursor-pointer file:border-0 file:border-r file:border-solid file:border-input file:bg-surface-muted file:px-4 file:text-sm file:font-semibold file:text-foreground hover:file:bg-border',
        'focus:outline-hidden focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30',
        'aria-invalid:border-danger disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-60',
        'group-data-enhanced/file-input:sr-only',
    ])->merge([
        'type' => 'file',
        'name' => $name,
        'id' => $fileId,
        'accept' => $accept,
        'multiple' => (bool) $multiple,
        'aria-invalid' => $fileInvalid ? 'true' : null,
        'aria-describedby' => $fileRules !== null ? $fileId.'-rules' : null,
        'data-slot' => 'control',
        'data-file-input-control' => true,
    ]) }}>
    <template data-file-input-dropzone-template>
        <label for="{{ $fileId }}" data-file-input-dropzone class="flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed border-input bg-surface px-6 py-6 text-center transition-colors duration-fast ease-smooth-out motion-reduce:transition-none hover:border-ring peer-focus-visible:border-ring peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring peer-aria-invalid:border-danger peer-disabled:cursor-not-allowed peer-disabled:opacity-60 data-dragging:border-ring data-dragging:bg-accent">
            <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-accent text-accent-foreground">
                <x-ui.icon name="arrow-up-tray" />
            </span>
            <span aria-hidden="true" class="text-sm text-muted-foreground">
                <span class="font-semibold text-link underline underline-offset-2">{{ $multiple ? 'Escolher arquivos' : 'Escolher arquivo' }}</span>
                ou arraste até aqui
            </span>
        </label>
    </template>
    <template data-file-input-item-template>
        <li class="flex items-center gap-3 rounded-control border border-border bg-surface py-1 pr-1 pl-2">
            <span data-file-input-item-preview class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-control bg-surface-muted text-muted-foreground">
                <x-ui.icon name="document-text" data-file-input-item-icon />
                <img data-file-input-item-image alt="" hidden class="size-full object-contain">
            </span>
            <span class="min-w-0 flex-1">
                <span data-file-input-item-name class="block truncate text-sm font-medium text-foreground"></span>
                <span data-file-input-item-size class="block text-xs text-muted-foreground"></span>
            </span>
            <button type="button" data-file-input-remove class="flex size-10 shrink-0 items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast ease-smooth-out hover:bg-danger-soft hover:text-danger motion-reduce:transition-none">
                <x-ui.icon name="x-mark" />
            </button>
        </li>
    </template>
    @if($fileRules !== null)
        <p id="{{ $fileId }}-rules" class="text-xs text-muted-foreground">{{ $fileRules }}</p>
    @endif
    <p id="{{ $fileId }}-feedback" data-file-input-feedback role="alert" class="flex items-start gap-1.5 text-sm text-danger empty:hidden"></p>
    <ul data-file-input-list class="grid gap-2" aria-label="Arquivos escolhidos" hidden></ul>
    <p data-file-input-status class="sr-only" aria-live="polite"></p>
</div>
