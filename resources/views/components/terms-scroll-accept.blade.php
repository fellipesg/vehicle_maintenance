@props([
    'name' => 'terms_accepted',
    'submitSelector' => '[data-terms-submit]',
])

@php
    $termsContent = config('legal.terms_of_use');
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-automotive-200 bg-white']) }} data-terms-scroll-accept>
    <div class="border-b border-automotive-200 px-4 py-3">
        <h3 class="text-base font-semibold text-automotive-900">Termos de uso</h3>
        <p class="mt-1 text-sm text-automotive-500">Role até o final para habilitar o aceite.</p>
    </div>
    <div class="terms-scroll-box max-h-56 overflow-y-auto px-4 py-3 text-sm leading-relaxed text-automotive-700 whitespace-pre-line" data-terms-scroll-box>{{ $termsContent }}</div>
    <div class="border-t border-automotive-200 px-4 py-3">
        <label class="flex items-start gap-3 text-sm text-automotive-700">
            <input type="checkbox"
                   name="{{ $name }}"
                   value="1"
                   disabled
                   data-terms-checkbox
                   @checked(old($name))
                   class="mt-0.5 rounded border-automotive-300 text-wrench-600 focus:ring-wrench-500 disabled:opacity-50">
            <span>Li e aceito os termos. Declaro que as informações prestadas são verdadeiras e de minha responsabilidade.</span>
        </label>
        @error($name)<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                document.querySelectorAll('[data-terms-scroll-accept]').forEach((root) => {
                    const box = root.querySelector('[data-terms-scroll-box]');
                    const checkbox = root.querySelector('[data-terms-checkbox]');
                    const form = root.closest('form');
                    const submitButtons = form
                        ? form.querySelectorAll(root.dataset.submitSelector || '[data-terms-submit], button[type="submit"]')
                        : [];

                    if (!box || !checkbox) {
                        return;
                    }

                    const unlockIfScrolled = () => {
                        const atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 8;
                        if (atBottom) {
                            checkbox.disabled = false;
                        }
                    };

                    box.addEventListener('scroll', unlockIfScrolled, { passive: true });
                    unlockIfScrolled();

                    const syncSubmit = () => {
                        const enabled = checkbox.checked;
                        submitButtons.forEach((button) => {
                            button.disabled = !enabled;
                        });
                    };

                    checkbox.addEventListener('change', syncSubmit);
                    syncSubmit();
                });
            })();
        </script>
    @endpush
@endonce
