{{--
    Aceite dos termos de uso ao registrar um veículo (assistente "Adicionar veículo", passos
    Documento e Conferir). A caixa rola o texto de config('legal.terms_of_use'); a caixa de
    marcação só libera depois de rolar até o fim (uma caixa oculta, dentro de <details> fechado,
    não conta como lida) e nunca volta marcada e desabilitada depois de um erro de validação.
    O botão de envio ([data-terms-submit]) fica com aria-disabled e o envio é barrado por JS até o
    aceite; o aviso em aria-live diz o que falta. Cores pelos papéis semânticos, como x-ui.checkbox.

    Props: name (terms_accepted), submit-selector ([data-terms-submit]), heading-level (h3).
--}}
@props([
    'name' => 'terms_accepted',
    'submitSelector' => '[data-terms-submit]',
    'headingLevel' => 'h3',
])

@php
    $termsContent = config('legal.terms_of_use');
    $headingNumber = (int) ltrim(strtolower((string) $headingLevel), 'h');
    $headingTag = $headingNumber >= 2 && $headingNumber <= 6 ? "h{$headingNumber}" : 'h3';
    $idPrefix = \Illuminate\Support\Str::slug($name);
    $previouslyAccepted = (bool) old($name);
    $scrollMessage = 'Para continuar, role os termos até o final e marque o aceite.';
    $checkMessage = 'Para continuar, marque o aceite dos termos.';
    $checkboxDescription = trim($idPrefix.'-hint '.($errors->has($name) ? $idPrefix.'-error' : ''));
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card border border-border bg-surface']) }}
     data-terms-scroll-accept
     data-submit-selector="{{ $submitSelector }}"
     @if($previouslyAccepted) data-terms-accepted @endif>
    <div class="border-b border-border px-4 py-3">
        <{{ $headingTag }} id="{{ $idPrefix }}-title" class="text-base font-semibold text-foreground">Termos de uso</{{ $headingTag }}>
        <p id="{{ $idPrefix }}-hint" class="mt-1 text-sm text-muted-foreground">
            Role o texto até o final para liberar o aceite. No teclado, use Tab até o texto e as setas para rolar.
        </p>
        <p class="mt-1 text-sm">
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-medium text-link underline underline-offset-2 hover:text-link-hover">Ler os termos completos<span class="sr-only"> (abre em nova aba)</span></a>
        </p>
    </div>
    <div class="terms-scroll-box max-h-56 overflow-y-auto px-4 py-3 text-sm leading-relaxed text-foreground whitespace-pre-line focus-visible:-outline-offset-2"
         tabindex="0"
         role="region"
         aria-labelledby="{{ $idPrefix }}-title"
         aria-describedby="{{ $idPrefix }}-hint"
         data-terms-scroll-box>{{ $termsContent }}</div>
    <div class="border-t border-border px-4 py-3">
        <label class="flex min-h-10 cursor-pointer items-start gap-3 py-1 text-sm text-foreground has-disabled:cursor-not-allowed">
            <input type="checkbox"
                   id="{{ $idPrefix }}-checkbox"
                   name="{{ $name }}"
                   value="1"
                   data-terms-checkbox
                   aria-describedby="{{ $checkboxDescription }}"
                   @error($name) aria-invalid="true" @enderror
                   @checked($previouslyAccepted)
                   class="mt-0.5 size-4 shrink-0 cursor-pointer rounded border-input text-accent-foreground not-checked:bg-surface transition-colors duration-fast ease-smooth-out focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-danger disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none">
            <span>Li e aceito os termos. Declaro que as informações prestadas são verdadeiras e de minha responsabilidade.</span>
        </label>
        @error($name)
            <p id="{{ $idPrefix }}-error" class="mt-2 flex items-start gap-1.5 text-sm text-danger">
                <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
                <span><span class="sr-only">Erro: </span>{{ $message }}</span>
            </p>
        @enderror
    </div>
    <p id="{{ $idPrefix }}-status"
       class="border-t border-border bg-surface-muted px-4 py-2 text-sm font-medium text-foreground"
       aria-live="polite"
       data-terms-status
       data-message-scroll="{{ $scrollMessage }}"
       data-message-check="{{ $checkMessage }}"
       @if($previouslyAccepted) hidden @endif>{{ $previouslyAccepted ? '' : $scrollMessage }}</p>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                document.querySelectorAll('[data-terms-scroll-accept]').forEach((root) => {
                    const box = root.querySelector('[data-terms-scroll-box]');
                    const checkbox = root.querySelector('[data-terms-checkbox]');
                    const status = root.querySelector('[data-terms-status]');
                    const form = root.closest('form');
                    const submitButtons = form
                        ? Array.from(form.querySelectorAll(root.dataset.submitSelector || '[data-terms-submit]'))
                        : [];

                    if (!box || !checkbox) {
                        return;
                    }

                    // Aceite que voltou marcado de um erro de validação já foi lido: nunca fica checked e disabled.
                    let unlocked = checkbox.checked || root.hasAttribute('data-terms-accepted');

                    const isReady = () => unlocked && checkbox.checked;

                    const render = () => {
                        const ready = isReady();
                        checkbox.disabled = !unlocked;

                        submitButtons.forEach((button) => {
                            button.disabled = false;
                            button.setAttribute('aria-disabled', ready ? 'false' : 'true');
                            button.classList.toggle('opacity-60', !ready);
                            button.classList.toggle('cursor-not-allowed', !ready);
                        });

                        if (status) {
                            status.textContent = ready
                                ? ''
                                : (unlocked ? status.dataset.messageCheck : status.dataset.messageScroll);
                            status.hidden = ready;
                        }
                    };

                    const unlockIfScrolled = () => {
                        // Caixa fora da tela (dentro de um <details> fechado) mede 0 de altura: não
                        // conta como lida. O toggle do <details> confere de novo quando ela aparece.
                        if (unlocked || box.clientHeight === 0) {
                            return;
                        }

                        const atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 8;
                        if (atBottom) {
                            unlocked = true;
                            render();
                        }
                    };

                    if (status) {
                        submitButtons.forEach((button) => {
                            const describedBy = new Set((button.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                            describedBy.add(status.id);
                            button.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
                        });
                    }

                    form?.addEventListener('submit', (event) => {
                        if (isReady() || (event.submitter && !submitButtons.includes(event.submitter))) {
                            return;
                        }

                        event.preventDefault();
                        (unlocked ? checkbox : box).focus();
                    });

                    box.addEventListener('scroll', unlockIfScrolled, { passive: true });
                    root.closest('details')?.addEventListener('toggle', unlockIfScrolled);
                    checkbox.addEventListener('change', render);
                    render();
                    unlockIfScrolled();
                });
            })();
        </script>
    @endpush
@endonce
