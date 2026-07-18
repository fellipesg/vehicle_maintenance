<div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-4 text-sm text-blue-900">
    <h2 class="font-semibold">Resumo do CRLV-e</h2>
    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
        <div><dt class="text-blue-700">Placa</dt><dd class="font-medium">{{ $preview['license_plate'] }}</dd></div>
        <div><dt class="text-blue-700">RENAVAM</dt><dd class="font-medium">{{ $preview['renavam'] }}</dd></div>
        @if(! empty($preview['crv_number']))
            <div><dt class="text-blue-700">Nº do CRV</dt><dd class="font-medium">{{ $preview['crv_number'] }}</dd></div>
        @endif
        @if(! empty($preview['exercise_year']))
            <div><dt class="text-blue-700">Exercício CRLV</dt><dd class="font-medium">{{ $preview['exercise_year'] }}</dd></div>
        @endif
        <div><dt class="text-blue-700">Marca / modelo (CRLV)</dt><dd class="font-medium">{{ $preview['brand_raw'] }} / {{ $preview['model_raw'] }}</dd></div>
        <div><dt class="text-blue-700">Marca / modelo (catálogo)</dt><dd class="font-medium">{{ $preview['brand'] }} / {{ $preview['model'] }}</dd></div>
        <div><dt class="text-blue-700">Ano do modelo</dt><dd class="font-medium">{{ $preview['year'] }}</dd></div>
        @if(! empty($preview['detran_state']))
            <div><dt class="text-blue-700">UF DETRAN</dt><dd class="font-medium">{{ $preview['detran_state'] }}</dd></div>
        @endif
        @if(! empty($preview['fuel']))
            <div><dt class="text-blue-700">Combustível</dt><dd class="font-medium">{{ $preview['fuel'] }}</dd></div>
        @endif
    </dl>

    @if(! $preview['brand_matched'] || ! $preview['model_matched'])
        <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
            Não encontramos correspondência exata no catálogo para
            @if(! $preview['brand_matched']) a marca @endif
            @if(! $preview['brand_matched'] && ! $preview['model_matched']) e @endif
            @if(! $preview['model_matched']) o modelo @endif.
            Ajuste manualmente nos campos abaixo, se necessário.
        </p>
    @endif
</div>
