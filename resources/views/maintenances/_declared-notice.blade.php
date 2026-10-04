{{--
    Aviso de procedência no topo de "Registrar manutenção" do Proprietário e do Lojista (e de
    "Editar manutenção" do Proprietário): antes do envio, diz que o registro aparece como Declarada
    pelo proprietário/lojista, com o mesmo bloco .prov-declared e o marcador PR/LJ dos cards e da
    x-provenance-legend (.ai/rules/theme.md). O Selo da oficina só vem quando a oficina da rede
    registra o serviço no portal dela ou confirma o que foi declarado (fila "Validações").

    Com config('maintenance.auto_verify_linked_workshop') (ambiente de testes), o aviso diz que
    escolher uma oficina da rede aplica o selo.

    Variáveis:
    - declaredBy: owner (o padrão, marcador "PR") | garage (marcador "LJ").
    - title: título no lugar de "Aparecerá como Declarada pelo …" (ex.: na edição).
--}}
@php
    $noticeByGarage = ($declaredBy ?? 'owner') === 'garage';
    $noticeDeclaredLabel = $noticeByGarage ? 'Declarada pelo lojista' : 'Declarada pelo proprietário';
    $noticeMarker = $noticeByGarage ? 'LJ' : 'PR';
    $noticeTitle = $title ?? 'Aparecerá como '.$noticeDeclaredLabel;
    $noticeAutoSeal = \App\Services\Maintenance\MaintenanceVerificationStamper::autoVerifyEnabled();
@endphp

@if ($noticeAutoSeal)
    <x-ui.alert variant="info" title="Selo da oficina neste ambiente de testes" data-provenance-notice="auto-verify">
        Aqui, escolher uma oficina da rede aplica o Selo da oficina. Em produção a manutenção aparece como {{ $noticeDeclaredLabel }}.
    </x-ui.alert>
@else
    <div class="prov-declared flex items-start gap-3 rounded-card border border-dashed border-prov-declared bg-prov-declared-surface p-4 text-sm" role="note" aria-labelledby="aviso-procedencia-titulo" data-provenance-notice="declared">
        <div class="prov-marker prov-marker--declared prov-marker--sm prov-declared mt-0.5" aria-hidden="true">{{ $noticeMarker }}</div>
        <div class="min-w-0 space-y-1">
            <p id="aviso-procedencia-titulo" class="font-semibold text-foreground">{{ $noticeTitle }}</p>
            <p class="text-muted-foreground">
                O Selo da oficina só é aplicado quando a oficina da rede registra o serviço no portal dela. Ao escolher uma oficina da rede aqui, ela recebe um pedido para confirmar o serviço; se confirmar, a manutenção ganha o selo.
                @unless ($noticeByGarage)
                    A nota fiscal ajuda a comprovar o que você declara.
                @endunless
            </p>
        </div>
    </div>
@endif
