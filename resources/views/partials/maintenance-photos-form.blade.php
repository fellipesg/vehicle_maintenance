@php
    $photoGroups = [
        'vehicle_before' => [
            'title' => 'Carro — antes do serviço',
            'help' => 'Tire o veículo inteiro em luz natural, sem flash, a ~3 m. Uma foto de cada lado (frente, traseira, esquerda, direita). Placa e avarias visíveis. Chão seco, fundo limpo. Não recorte demais.',
        ],
        'vehicle_after' => [
            'title' => 'Carro — depois do serviço',
            'help' => 'Repita os MESMOS ângulos do antes (mesma distância e altura). Mostre o resultado: pintura, faróis, pneus, área reparada. Sem filtros. Se limpou o carro, fotografe limpo.',
        ],
        'part_before' => [
            'title' => 'Peças — estado ao retirar / como chegou',
            'help' => 'Cada peça crítica em close, bem iluminada, sobre fundo neutro. Inclua número de peça/código se houver. Mostre desgaste, vazamento, quebra. Uma foto da peça no carro (contexto) e uma na bancada.',
        ],
        'part_after' => [
            'title' => 'Peças — novas ou após o serviço',
            'help' => 'Mesmos enquadramentos do antes. Peça nova na embalagem (se aplicável) e já instalada. Código da peça legível. Serve para o cliente comparar o que saiu e o que entrou.',
        ],
    ];
@endphp

<div class="space-y-6">
    @foreach($photoGroups as $field => $group)
        <div class="rounded-lg border border-automotive-100 p-4">
            <h3 class="font-semibold">{{ $group['title'] }}</h3>
            <p class="mt-1 text-sm text-automotive-600">{{ $group['help'] }}</p>

            @if(!empty($existingPhotos) && isset($existingPhotos[$field]) && $existingPhotos[$field]->isNotEmpty())
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach($existingPhotos[$field] as $photo)
                        <label class="block">
                            <img src="{{ $photo->url }}" alt="{{ $photo->original_name }}" class="h-24 w-full rounded-lg object-cover">
                            @if($editable ?? false)
                                <span class="mt-1 flex items-center gap-2 text-xs text-automotive-600">
                                    <input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}" class="rounded">
                                    Remover
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="mt-3">
                <label class="form-label" for="photos_{{ $field }}">Adicionar fotos (máx. 4)</label>
                <input type="file" name="photos[{{ $field }}][]" id="photos_{{ $field }}"
                       accept="image/jpeg,image/png,image/webp" multiple class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-wrench-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-wrench-700">
            </div>
            @error("photos.$field")<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @error("photos.$field.*")<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endforeach
</div>
