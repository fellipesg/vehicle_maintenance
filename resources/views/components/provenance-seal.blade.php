{{--
    Bloco de procedência no detalhe da manutenção (contrato de .ai/rules/theme.md).

    Selo da oficina: nome e logo da oficina (inteiro, object-contain numa moldura clara: logo não é
    cortado), data de emissão, "Atualizada em" quando a OS mudou depois do selo
    (Maintenance::wasUpdatedAfterSeal), código de verificação em mono, QR code (SVG na tela; o PDF
    usa só o código) e as ações Copiar código, Copiar link, Compartilhar (só onde o navegador tem
    compartilhamento nativo, via resources/js/provenance-actions.js) e Abrir verificação.
    Declarada: quem declarou, o aviso de que não tem Selo da oficina (citando a oficina cadastrada,
    quando o registro aponta uma) e as evidências (NF-e e fotos).

    Props: maintenance (obrigatório; verifiedWorkshop, invoices e photos são usados se carregados).
--}}
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
    $workshopName = $maintenance->verifiedWorkshop?->name ?? $maintenance->workshop_name;
    $updatedAfterSeal = $verified && $maintenance->wasUpdatedAfterSeal();
    // Horário de Brasília, o mesmo do /v/{código} e do PDF (App\Support\DisplayTime).
    $sealedAt = \App\Support\DisplayTime::local($maintenance->verified_at);
    $updatedAt = \App\Support\DisplayTime::local($maintenance->updated_at);
    $declaredBy = $maintenance->registered_by_type === 'garage' ? 'pelo lojista' : 'pelo dono do veículo';
    $citedWorkshopName = ! $verified && $maintenance->workshop_id ? $maintenance->displayWorkshopName() : null;
@endphp

@if ($verified)
    <div {{ $attributes->class(['prov-seal prov-verified']) }} data-slot="provenance-seal">
        <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-left">
            @if ($maintenance->verifiedWorkshop?->logoUrl())
                <img
                    src="{{ $maintenance->verifiedWorkshop->logoUrl() }}"
                    alt=""
                    class="size-16 shrink-0 rounded-control bg-surface object-contain p-1.5 ring-1 ring-border"
                    data-slot="provenance-seal-logo"
                >
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--prov-ink)]">Selo da oficina</p>
                @if ($workshopName)
                    <p class="mt-1 text-lg font-semibold text-foreground">{{ $workshopName }}</p>
                @endif
                @if ($sealedAt)
                    <p class="text-sm text-muted-foreground">Emitido em <time datetime="{{ $sealedAt->toIso8601String() }}" class="tabular-nums">{{ $sealedAt->format('d/m/Y') }}</time></p>
                @endif
                @if ($updatedAfterSeal)
                    <p class="text-sm text-muted-foreground" data-updated-after-seal>
                        Atualizada em <time datetime="{{ $updatedAt->toIso8601String() }}" class="tabular-nums">{{ $updatedAt->format('d/m/Y') }}</time>, depois da emissão do selo.
                    </p>
                @endif
                <p class="mt-2 text-sm text-muted-foreground">
                    Código de verificação
                    <span class="font-mono text-foreground">{{ $maintenance->verification_code }}</span>
                </p>
            </div>
            @if ($maintenance->verificationUrl())
                <div class="shrink-0" role="img" aria-label="QR code para conferir este selo">
                    {!! \App\Support\VerificationQr::svg($maintenance->verificationUrl(), 96) !!}
                </div>
            @endif
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            {{-- Copiar: x-ui.copy-button (check por 1,5s e o aviso anunciado ao leitor de tela). --}}
            <x-ui.copy-button :value="$maintenance->verification_code" label="Copiar código" copied-label="Código copiado" />
            @if ($maintenance->verificationUrl())
                <x-ui.copy-button :value="$maintenance->verificationUrl()" label="Copiar link" copied-label="Link copiado" />
                {{-- Só aparece (resources/js/provenance-actions.js) onde o navegador tem compartilhamento nativo. --}}
                <x-ui.button variant="secondary" size="sm" data-share-verification :data-url="$maintenance->verificationUrl()" hidden>Compartilhar</x-ui.button>
                <x-ui.button variant="secondary" size="sm" :href="$maintenance->verificationUrl()" icon-trailing="arrow-top-right-on-square" target="_blank" rel="noopener">
                    Abrir verificação<span class="sr-only"> (abre em nova aba)</span>
                </x-ui.button>
            @endif
        </div>
    </div>
@else
    <div {{ $attributes->class(['prov-seal prov-seal--declared prov-declared p-4']) }} data-slot="provenance-seal">
        <div class="flex gap-3">
            <x-provenance-marker :maintenance="$maintenance" />
            <div class="min-w-0 flex-1 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">{{ $maintenance->provenance_label }} <span class="font-normal text-muted-foreground">({{ $maintenance->provenance_sublabel }})</span></p>
                <p class="mt-2" data-slot="provenance-seal-declared-note">
                    @if ($citedWorkshopName)
                        Este registro foi feito {{ $declaredBy }} e cita a oficina {{ $citedWorkshopName }}, cadastrada no RevisaLog, mas não tem o Selo da oficina: a oficina não confirmou o serviço.
                    @elseif ($maintenance->registered_by_type === 'garage')
                        Este registro foi feito pelo lojista e não passou por uma oficina cadastrada na plataforma.
                    @else
                        Este registro foi feito pelo dono do veículo e não passou por uma oficina cadastrada.
                    @endif
                </p>
                @if ($invoiceCount > 0 || $photoCount > 0)
                    <p class="mt-2">
                        Evidências:
                        @if ($invoiceCount > 0)
                            {{ $invoiceCount === 1 ? '1 NF-e' : $invoiceCount.' NF-e' }}
                        @endif
                        @if ($photoCount > 0)
                            @if ($invoiceCount > 0)
                                <span aria-hidden="true">·</span>
                            @endif
                            {{ $photoCount === 1 ? '1 foto' : $photoCount.' fotos' }}
                        @endif
                    </p>
                @else
                    <p class="mt-2">Sem nota fiscal nem fotos anexadas.</p>
                @endif
                @if ($maintenance->workshop_name && ! $maintenance->workshop_id)
                    <p class="mt-2">
                        Oficina informada: {{ $maintenance->workshop_name }} (não confirmada)
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
