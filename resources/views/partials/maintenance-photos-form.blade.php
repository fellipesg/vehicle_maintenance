{{--
    Etapa "Fotos" do formulário de OS: quatro grupos (carro e peças, antes e depois), cada um com
    até MaintenancePhoto::MAX_PER_GROUP fotos contando as que já existem.

    - Cada grupo é um <fieldset> com título (h3), o contador "N de 4 fotos" (atualizado por
      resources/js/workshop-maintenance-form.js) e a orientação recolhida em "Como fotografar".
    - Foto existente (edição): a miniatura inteira (object-contain) abre a foto; "Remover" é um
      checkbox próprio, fora da imagem, e a foto marcada fica apagada com o aviso "Será removida
      ao salvar" (desmarque para desfazer).
    - Novas fotos: <x-ui.file-input> com prévia, limite de 5 MB por foto e o limite do grupo.

    Variáveis: $existingPhotos (fotos da OS agrupadas por "assunto_etapa"), $editable (edição) e
    $sectionNumber.
--}}
@php
    use App\Models\MaintenancePhoto;

    $existingPhotos = $existingPhotos ?? collect();
    $editable = $editable ?? false;
    $maxPerGroup = MaintenancePhoto::MAX_PER_GROUP;
    $markedForRemoval = collect(old('delete_photos', []))->map(fn ($id) => (int) $id)->all();
    $photoGroups = [
        'vehicle_before' => [
            'title' => 'Carro antes do serviço',
            'help' => 'O carro inteiro em luz natural, sem flash, a uns 3 metros: frente, traseira e os dois lados. Deixe a placa e as avarias visíveis, com fundo limpo.',
        ],
        'vehicle_after' => [
            'title' => 'Carro depois do serviço',
            'help' => 'Repita os mesmos ângulos do antes, na mesma distância e altura. Mostre o resultado (pintura, faróis, pneus, área reparada), sem filtros.',
        ],
        'part_before' => [
            'title' => 'Peças ao retirar',
            'help' => 'Cada peça importante em close, bem iluminada e sobre fundo neutro, com o código visível. Mostre o desgaste, o vazamento ou a quebra: uma foto no carro e uma na bancada.',
        ],
        'part_after' => [
            'title' => 'Peças novas ou depois do serviço',
            'help' => 'Os mesmos enquadramentos do antes: a peça nova (na embalagem, se houver) e já instalada, com o código legível. Assim o cliente compara o que saiu e o que entrou.',
        ],
    ];
@endphp

<x-ui.form-section
    id="secao-fotos"
    :number="$sectionNumber ?? null"
    title="Fotos"
    :description="'Até '.$maxPerGroup.' fotos por grupo, de até 5 MB cada (JPG, PNG ou WebP). As fotos do carro depois do serviço aparecem para o cliente na ficha do veículo.'"
>
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach($photoGroups as $field => $group)
            @php
                $groupPhotos = $existingPhotos[$field] ?? collect();
                $groupId = 'photos_'.$field;
                $keptCount = $groupPhotos->reject(fn ($photo) => in_array($photo->id, $markedForRemoval, true))->count();
                $room = max(0, $maxPerGroup - $keptCount);
            @endphp
            <fieldset class="min-w-0 rounded-control border border-border p-4" id="{{ $groupId }}-grupo"
                      aria-describedby="{{ $groupId }}-count"
                      data-photo-group data-max="{{ $maxPerGroup }}" data-label="{{ $group['title'] }}">
                <legend class="float-left w-full"><h3 class="text-base font-semibold text-foreground">{{ $group['title'] }}</h3></legend>
                <p id="{{ $groupId }}-count" class="clear-left pt-1 text-sm text-muted-foreground tabular-nums" data-photo-count aria-live="polite">
                    {{ $keptCount }} de {{ $maxPerGroup }} fotos · @if($room > 0)você pode adicionar {{ $keptCount === 0 ? 'até' : 'mais' }} {{ $room }}@else limite atingido: remova uma para trocar @endif
                </p>

                <details class="group mt-2 clear-left">
                    <summary class="inline-flex min-h-10 cursor-pointer list-none items-center gap-1.5 rounded-control text-sm font-medium text-link hover:text-link-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                        <x-ui.icon name="camera" class="size-4" />
                        Como fotografar
                        <x-ui.icon name="chevron-down" class="size-4 transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
                    </summary>
                    <p class="pb-2 text-sm text-muted-foreground">{{ $group['help'] }}</p>
                </details>

                @if($groupPhotos->isNotEmpty())
                    <ul role="list" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2" aria-label="Fotos salvas: {{ $group['title'] }}">
                        @foreach($groupPhotos as $photo)
                            <li class="group/photo relative min-w-0" data-photo-item>
                                <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                                   class="block overflow-hidden rounded-control border border-border bg-surface-muted transition-opacity duration-fast ease-smooth-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none group-has-[:checked]/photo:border-dashed group-has-[:checked]/photo:border-danger group-has-[:checked]/photo:opacity-40 group-has-[:checked]/photo:grayscale">
                                    <img src="{{ $photo->url }}" alt="{{ $group['title'] }}, foto {{ $loop->iteration }} de {{ $groupPhotos->count() }}" loading="lazy" class="aspect-[4/3] w-full object-contain">
                                    <span class="sr-only">(abre em nova aba)</span>
                                </a>
                                <span class="mt-1 hidden items-center gap-1 text-xs font-medium text-danger group-has-[:checked]/photo:flex" aria-hidden="true">
                                    <x-ui.icon name="trash" class="size-3.5" />
                                    Será removida ao salvar
                                </span>
                                @if($editable)
                                    <label class="mt-1 flex min-h-10 cursor-pointer items-center gap-2 rounded-control text-sm text-foreground has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring">
                                        <input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}" data-photo-remove
                                               @checked(in_array($photo->id, $markedForRemoval, true))
                                               class="size-4 rounded border-input text-danger focus:ring-0 focus:ring-offset-0">
                                        <span>Remover<span class="sr-only"> {{ mb_strtolower($group['title']) }}, foto {{ $loop->iteration }}</span></span>
                                    </label>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-3">
                    <x-ui.field :name="'photos['.$field.'][]'" :label="'Adicionar fotos: '.mb_strtolower($group['title'])" label-sr-only>
                        <x-ui.file-input accept="image/jpeg,image/png,image/webp" multiple :max-mb="5" :max-files="$maxPerGroup"
                                         :rules="false" data-photo-input />
                    </x-ui.field>
                    <p id="{{ $groupId }}-limit" class="mt-2 flex items-start gap-1.5 text-sm text-danger empty:hidden" role="alert" tabindex="-1" data-photo-limit></p>
                </div>
            </fieldset>
        @endforeach
    </div>
</x-ui.form-section>
