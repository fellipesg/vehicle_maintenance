@props([
    'src',
    'alt',
])

<figure {{ $attributes->class([]) }}>
    <div class="overflow-hidden rounded-[2.4rem] border-[10px] border-zinc-900 bg-zinc-900 shadow-2xl ring-1 ring-white/15">
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            class="block h-auto w-full"
            width="390"
            height="844"
        >
    </div>
</figure>
