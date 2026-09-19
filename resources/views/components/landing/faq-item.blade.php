@props([
    'question',
])

<details {{ $attributes->class(['landing-faq card group !p-0']) }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-automotive-900">
        <span>{{ $question }}</span>
        <span class="shrink-0 text-xl leading-none text-wrench-600 transition group-open:rotate-45" aria-hidden="true">+</span>
    </summary>
    <div class="border-t border-automotive-100 px-5 pb-5 pt-3 text-sm leading-relaxed text-automotive-600">
        {{ $slot }}
    </div>
</details>
