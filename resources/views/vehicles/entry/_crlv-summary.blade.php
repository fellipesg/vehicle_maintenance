{{--
    O que o CRLV-e trouxe (passos Conferir e Procuração), em lista de termos. O CPF/CNPJ do
    proprietário sai parcial (App\Support\DocumentMask); no Lojista, o da conta aparece ao lado, para
    explicar por que o veículo entra como da loja ou em consignação.

    Variáveis: $preview (crlv_verification.parsed), $flow, $accountDocument (documento da conta) e
    $catalogHint (false tira o aviso de marca/modelo fora do catálogo, em telas sem o formulário).
--}}
@php
    $summaryOwnerDocument = \App\Support\DocumentMask::cpfOrCnpj($preview['owner_document'] ?? null);
    $summaryAccountDocument = \App\Support\DocumentMask::cpfOrCnpj($accountDocument ?? null);
    $summaryRawModel = trim(($preview['brand_raw'] ?? '').' / '.($preview['model_raw'] ?? ''), ' /');
    $summaryBrandMatched = (bool) ($preview['brand_matched'] ?? true);
    $summaryModelMatched = (bool) ($preview['model_matched'] ?? true);
    $summaryMissingCatalog = array_values(array_filter([
        $summaryBrandMatched ? null : 'a marca',
        $summaryModelMatched ? null : 'o modelo',
    ]));
    $summaryRows = array_filter([
        'Placa' => ['value' => $preview['license_plate'] ?? null, 'mono' => true],
        'RENAVAM' => ['value' => $preview['renavam'] ?? null, 'mono' => true],
        'Número do CRV' => ['value' => $preview['crv_number'] ?? null, 'mono' => true],
        'Exercício do CRLV-e' => ['value' => $preview['exercise_year'] ?? null],
        'Marca e modelo no CRLV-e' => ['value' => $summaryRawModel !== '' ? $summaryRawModel : null],
        'Marca e modelo no catálogo' => ['value' => trim(($preview['brand'] ?? '').' '.($preview['model'] ?? '')) ?: null],
        'Ano do modelo' => ['value' => $preview['year'] ?? null],
        'UF do DETRAN' => ['value' => $preview['detran_state'] ?? null],
        'Combustível' => ['value' => $preview['fuel'] ?? null],
        'Proprietário no CRLV-e' => ['value' => trim(($preview['owner_name'] ?? '').($summaryOwnerDocument !== null ? ' · '.$summaryOwnerDocument : '')) ?: null],
        'CPF/CNPJ da sua conta' => $flow->isDealer() ? ['value' => $summaryAccountDocument ?? 'Não informado', 'muted' => $summaryAccountDocument === null] : null,
    ], fn (?array $row): bool => $row !== null && filled($row['value']));
@endphp
<dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2" data-crlv-summary>
    @foreach($summaryRows as $summaryLabel => $summaryRow)
        <div class="min-w-0">
            <dt class="text-muted-foreground">{{ $summaryLabel }}</dt>
            <dd @class([
                'mt-0.5 break-words',
                'font-mono font-semibold tracking-wider text-foreground' => $summaryRow['mono'] ?? false,
                'font-medium text-foreground' => ! ($summaryRow['mono'] ?? false) && ! ($summaryRow['muted'] ?? false),
                'font-medium text-muted-foreground' => $summaryRow['muted'] ?? false,
            ])>{{ $summaryRow['value'] }}</dd>
        </div>
    @endforeach
</dl>

@if($summaryMissingCatalog !== [] && ($catalogHint ?? true))
    <x-ui.alert variant="warning" role="status" class="mt-4" title="Confira a marca e o modelo" data-catalog-mismatch>
        Não encontramos no catálogo {{ implode(' e ', $summaryMissingCatalog) }} exatamente como {{ count($summaryMissingCatalog) > 1 ? 'estão' : 'está' }} no CRLV-e. Escolha a opção certa nos campos abaixo.
    </x-ui.alert>
@endif
