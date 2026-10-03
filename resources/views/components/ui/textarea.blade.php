{{--
    Texto de várias linhas.

    Props:
    - name / id / bag / invalid: como no <x-ui.input> (name e bag vêm do <x-ui.field> por @aware).
    - value: texto inicial (ou o slot, já escapado pelo Blade). Depois de um envio com erro vale
      old(name).
    - rows: 4 por padrão.
    - autosize: cresce com o texto (field-sizing: content) entre 6rem e 24rem, onde o navegador
      suporta; nos demais fica com a altura de rows e o puxador.
    - counter: contador "120/4.000" abaixo do campo, à direita. Exige o atributo maxlength. Fica em
      {id}-contador, ligado ao campo por aria-describedby (o leitor de tela ouve "120 de 4.000
      caracteres" ao focar). resources/js/ui/textarea-counter.js atualiza a cada tecla e avisa na
      região aria-live polite a cada 10% do limite. Perto do fim (90%) o número fica em
      text-warning; no limite, em text-danger.

    Os demais atributos (maxlength, placeholder, required, data-*) e class vão para o <textarea>.

    Ex.: <x-ui.textarea name="notes" rows="6" maxlength="4000" />
         <x-ui.field name="message" label="Mensagem"><x-ui.textarea rows="6" maxlength="4000" counter /></x-ui.field>
--}}@aware([
    'name' => null,
    'bag' => null,
])
@props([
    'name' => null,
    'id' => null,
    'value' => null,
    'rows' => 4,
    'autosize' => false,
    'counter' => false,
    'bag' => null,
    'invalid' => false,
])
@php
    $textareaId = $id ?? \App\Support\FormField::controlId($name);
    $textareaError = \App\Support\FormField::error($errors ?? null, $name, $bag);
    $textareaInvalid = (bool) $invalid || $textareaError !== null;
    $textareaMissingOld = new \stdClass;
    $textareaOld = \App\Support\FormField::old($name, $textareaMissingOld);
    $textareaHtml = match (true) {
        $textareaOld !== $textareaMissingOld => e(is_scalar($textareaOld) ? (string) $textareaOld : ''),
        filled($value) => e((string) $value),
        default => $slot->toHtml(),
    };

    $textareaHasCounter = (bool) $counter;
    $textareaMaxLength = (int) $attributes->get('maxlength', 0);

    if ($textareaHasCounter) {
        \App\Support\UiProps::required('x-ui.textarea', 'maxlength', $textareaMaxLength > 0 ? (string) $textareaMaxLength : null, 'O contador (counter) mostra quanto falta até o maxlength.');
        \App\Support\UiProps::required('x-ui.textarea', 'id', $textareaId, 'O contador precisa de name ou id para o aria-describedby.');
    }

    $textareaCounterOn = $textareaHasCounter && $textareaMaxLength > 0 && filled($textareaId);
    $textareaCounterId = $textareaCounterOn ? $textareaId.'-contador' : null;
    // Mesma conta do maxlength do navegador (unidades UTF-16), com quebra de linha valendo 1.
    $textareaLength = $textareaCounterOn
        ? intdiv(strlen((string) mb_convert_encoding(str_replace("\r\n", "\n", html_entity_decode($textareaHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-16LE', 'UTF-8')), 2)
        : 0;
    $textareaCounterState = match (true) {
        ! $textareaCounterOn => null,
        $textareaLength >= $textareaMaxLength => 'limit',
        $textareaLength >= $textareaMaxLength * 0.9 => 'near',
        default => 'ok',
    };
    $textareaNumber = fn (int $number): string => number_format($number, 0, ',', '.');
    $textareaAttributes = $attributes
        ->except($textareaCounterOn ? ['aria-describedby'] : [])
        ->class([
            'form-input min-h-24 text-base sm:text-sm',
            'resize-y' => ! $autosize,
            'field-sizing-content max-h-96 resize-none' => $autosize,
            'transition-[border-color,box-shadow] duration-fast ease-smooth-out motion-reduce:transition-none',
            'aria-invalid:border-danger aria-invalid:focus:ring-danger/60',
        ])
        ->merge([
            'name' => $name,
            'id' => $textareaId,
            'rows' => $rows,
            'aria-invalid' => $textareaInvalid ? 'true' : null,
            'aria-describedby' => $textareaCounterOn
                ? \App\Support\FormField::describedBy($attributes->get('aria-describedby'), $textareaCounterId)
                : null,
            'data-slot' => 'control',
            'data-textarea-counter' => $textareaCounterId,
        ]);
@endphp
@if($textareaCounterOn)
<div class="grid min-w-0 gap-1" data-slot="textarea-counter-root">
<textarea {{ $textareaAttributes }}>{!! $textareaHtml !!}</textarea>
    <p
        id="{{ $textareaCounterId }}"
        class="justify-self-end text-xs tabular-nums text-subtle-foreground transition-colors duration-fast ease-smooth-out motion-reduce:transition-none data-[state=near]:text-warning data-[state=limit]:font-medium data-[state=limit]:text-danger"
        data-slot="textarea-counter"
        data-state="{{ $textareaCounterState }}"
        data-max="{{ $textareaMaxLength }}"
    ><span aria-hidden="true" data-textarea-counter-visual>{{ $textareaNumber($textareaLength) }}/{{ $textareaNumber($textareaMaxLength) }}</span><span class="sr-only" data-textarea-counter-text>{{ $textareaNumber($textareaLength) }} de {{ $textareaNumber($textareaMaxLength) }} caracteres</span></p>
    <p class="sr-only" aria-live="polite" aria-atomic="true" data-textarea-counter-live></p>
</div>
@else
<textarea {{ $textareaAttributes }}>{!! $textareaHtml !!}</textarea>
@endif
