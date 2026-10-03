{{--
    Área do mapa do admin: o mapa Leaflet (#admin-map, montado por resources/js/admin-map.js) e, ao
    lado, a mesma lista em texto, que é a alternativa para teclado e leitor de tela e funciona sem o
    mapa. Com o mapa carregado, cada item ganha "Mostrar no mapa" (centraliza e abre o popup).

    Os pinos vão num <script type="application/json"> (só id, name, lat, lng, city e label, com
    @json, que escapa < e >); o link de cada cadastro vem de $pinLinks e fica na lista, e o popup
    copia esse href. O popup é montado com nós DOM e textContent: nome e endereço nunca viram HTML.

    O mapa usa isolate: os z-index altos do Leaflet (400 a 1000) ficam presos nele e não passam por
    cima da topbar, do menu de conta nem da gaveta do celular.

    Variáveis: $pins, $pinLinks ([id => url]), $mapLabel, $listTitle, $emptyTitle, $emptyDescription
    e $tone (workshop | owner: a cor do pino, lida dos tokens de resources/css/app.css).
--}}
<div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]" data-admin-map-root>
    <script type="application/json" data-admin-map-pins>@json($pins)</script>

    <div class="relative isolate h-[60dvh] min-h-80 w-full lg:h-auto lg:min-h-[28rem]">
        <div
            id="admin-map"
            role="region"
            aria-label="{{ $mapLabel }}"
            data-admin-map
            data-admin-map-tone="{{ $tone }}"
            class="isolate h-full w-full rounded-card border border-border bg-surface-muted shadow-inner"
        ></div>
        <div
            class="absolute inset-0 flex items-center justify-center rounded-card p-6 text-center text-sm text-muted-foreground"
            role="status"
            data-admin-map-status
        >
            <p class="flex items-center gap-2">
                <x-ui.spinner size="sm" />
                Carregando o mapa…
            </p>
        </div>
        <noscript>
            <p class="absolute inset-0 flex items-center justify-center rounded-card bg-surface-muted p-6 text-center text-sm text-muted-foreground">O mapa precisa de JavaScript. A lista ao lado tem os mesmos pontos.</p>
        </noscript>
    </div>

    <section aria-labelledby="admin-map-lista-titulo" class="flex min-h-0 flex-col rounded-card border border-border bg-surface">
        <h2 id="admin-map-lista-titulo" class="shrink-0 border-b border-border px-4 py-3 text-sm font-semibold text-foreground">
            {{ $listTitle }} <span class="font-normal text-muted-foreground tabular-nums">({{ count($pins) }})</span>
        </h2>
        @if(count($pins) === 0)
            <x-ui.empty-state icon="map-pin" :title="$emptyTitle" :description="$emptyDescription" variant="plain" size="sm" heading-level="p" />
        @else
            <ul role="list" class="max-h-72 divide-y divide-border overflow-y-auto lg:max-h-none lg:min-h-0 lg:flex-1">
                @foreach($pins as $pin)
                    @php
                        $pinLink = $pinLinks[$pin['id']] ?? null;
                    @endphp
                    <li class="flex items-start gap-2 px-4 py-2 text-sm" data-admin-map-item="{{ $pin['id'] }}">
                        <div class="min-w-0 flex-1 py-0.5">
                            @if($pinLink)
                                <x-ui.link :href="$pinLink" data-admin-map-pin-link="{{ $pin['id'] }}">{{ $pin['name'] }}</x-ui.link>
                            @else
                                <span class="font-medium text-foreground">{{ $pin['name'] }}</span>
                            @endif
                            @if(filled($pin['label'] ?? null))
                                <span class="block text-xs text-muted-foreground">{{ $pin['label'] }}</span>
                            @elseif(filled($pin['city'] ?? null))
                                <span class="block text-xs text-muted-foreground">{{ $pin['city'] }}</span>
                            @endif
                        </div>
                        <x-ui.icon-button
                            icon="map-pin"
                            size="sm"
                            :label="'Mostrar '.$pin['name'].' no mapa'"
                            data-admin-map-focus="{{ $pin['id'] }}"
                            aria-controls="admin-map"
                            hidden
                        />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
