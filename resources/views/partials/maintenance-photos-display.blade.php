@php
    $groups = [
        'vehicle_before' => 'Carro — antes do serviço',
        'vehicle_after' => 'Carro — depois do serviço',
        'part_before' => 'Peças — estado ao retirar / como chegou',
        'part_after' => 'Peças — novas ou após o serviço',
    ];
    $grouped = $maintenance->photos->groupBy(fn ($photo) => $photo->subject.'_'.$photo->stage);
@endphp

@if($maintenance->photos->isNotEmpty())
    <div class="card mt-6">
        <h2 class="mb-4 font-semibold">Fotos da OS</h2>
        <div class="space-y-6">
            @foreach($groups as $key => $title)
                @if($grouped->has($key))
                    <div>
                        <h3 class="text-sm font-semibold text-automotive-700">{{ $title }}</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach($grouped[$key] as $photo)
                                <a href="{{ $photo->url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-automotive-100">
                                    <img src="{{ $photo->url }}" alt="{{ $photo->original_name }}" class="h-28 w-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
