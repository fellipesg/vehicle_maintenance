@props([
    'maintenance',
])

@php
    $verified = $maintenance->isVerified();
    $invoiceCount = $maintenance->relationLoaded('invoices')
        ? $maintenance->invoices->count()
        : $maintenance->invoices()->count();
    $photoCount = $maintenance->relationLoaded('photos')
        ? $maintenance->photos->count()
        : $maintenance->photos()->count();
@endphp

@if ($verified)
    <div {{ $attributes->class(['prov-seal prov-verified mt-6']) }}>
        <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-left">
            @if ($maintenance->verifiedWorkshop?->logoUrl())
                <img
                    src="{{ $maintenance->verifiedWorkshop->logoUrl() }}"
                    alt=""
                    class="h-16 w-16 rounded-full object-cover"
                >
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-lg font-semibold text-automotive-900">{{ $maintenance->verifiedWorkshop?->name }}</p>
                <p class="text-sm font-medium text-teal-800">Registro verificado</p>
                <p class="text-sm text-automotive-600">
                    {{ $maintenance->verified_at?->format('d/m/Y') }}
                </p>
                <p class="mt-2 font-mono text-sm text-automotive-800">{{ $maintenance->verification_code }}</p>
            </div>
            @if ($maintenance->verificationUrl())
                <div class="shrink-0">
                    {!! \App\Support\VerificationQr::svg($maintenance->verificationUrl(), 96) !!}
                </div>
            @endif
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="btn-secondary text-sm"
                data-copy-verification-code
                data-code="{{ $maintenance->verification_code }}"
            >
                Copiar código
            </button>
            @if ($maintenance->verificationUrl())
                <a href="{{ $maintenance->verificationUrl() }}" class="btn-secondary text-sm" target="_blank" rel="noopener">
                    Abrir verificação
                </a>
            @endif
        </div>
    </div>
@else
    <div {{ $attributes->class(['prov-seal prov-seal--declared prov-declared mt-6 p-4']) }}>
        <div class="flex gap-3">
            <x-provenance-marker :maintenance="$maintenance" />
            <div class="min-w-0 flex-1 text-sm text-automotive-700">
                <p class="font-medium text-automotive-900">{{ $maintenance->provenance_label }}</p>
                <p class="mt-1">{{ $maintenance->provenance_sublabel }}</p>
                <p class="mt-2 text-automotive-600">
                    @if ($maintenance->registered_by_type === 'garage')
                        Este registro foi feito pelo lojista e não passou por uma oficina cadastrada na plataforma.
                    @else
                        Este registro foi feito pelo dono do veículo e não passou por uma oficina cadastrada.
                    @endif
                </p>
                @if ($invoiceCount > 0 || $photoCount > 0)
                    <p class="mt-2 text-automotive-600">
                        Evidências:
                        @if ($invoiceCount > 0)
                            NF-e {{ $invoiceCount }}
                        @endif
                        @if ($photoCount > 0)
                            @if ($invoiceCount > 0)
                                ·
                            @endif
                            fotos {{ $photoCount }}
                        @endif
                    </p>
                @endif
                @if ($maintenance->workshop_name && ! $maintenance->workshop_id)
                    <p class="mt-2 text-automotive-600">
                        Oficina informada: {{ $maintenance->workshop_name }} (não confirmada)
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
