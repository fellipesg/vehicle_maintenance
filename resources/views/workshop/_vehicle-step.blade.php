{{--
    Etapa 1 das telas da oficina que começam escolhendo um veículo: a Nova OS e a Nova vistoria
    cautelar. Fica fora do formulário principal.

    - Sem veículo: form GET próprio (a placa vai para ?license_plate=) com a máscara de placa.
      Placa sem cadastro mostra o estado vazio "Veículo ... ainda não está no RevisaLog", com o
      convite para o cliente e "Tentar outra placa".
    - Com veículo: card do veículo (role="status") com miniatura, placa, ano, cor e a última
      quilometragem, e "Trocar veículo". Na edição ($readonly) o card não tem troca.
      Sem $showsFullVehicle (veículo achado pela placa e sem OS desta oficina — com dono ou sem, ver
      App\Support\Workshop\WorkshopVehicleVisibility) o card fica no que a consulta pública mostra:
      a placa de volta é a que foi digitada, nunca a atual do veículo, e a quilometragem sai. Na
      edição de um laudo não há placa digitada: sem vínculo o card diz "Placa não exibida", que é
      outra coisa de "Placa não informada" (o veículo cadastrado pelo chassi, que não tem placa).

    Pelo chassi (?chassis=): veículo com proprietário não mostra nada do carro (só manda usar a placa);
    sem proprietário é reaproveitado e aparece inteiro (os 17 caracteres exatos são a posse do carro);
    inexistente é criado junto com a OS ou com o laudo (marca, modelo e ano).

    Variáveis: $context ('maintenance' ou 'inspection', obrigatório — é o que decide a rota da busca
    e o texto; sem ele a view quebra, em vez de mandar a oficina para a tela errada em silêncio),
    $vehicle, $licensePlate, $chassis, $chassisOwned, $chassisInvalid, $creatingVehicle, $lookup
    (App\Support\Vehicle\VehicleLookupResult), $vehicleHasOwner, $showsFullVehicle e $readonly.
    Tudo menos $readonly e $context vem de App\Support\Workshop\WorkshopVehicleStep::resolve().
    $showsFullVehicle é sempre explícito: quem esquecer recebe o card reduzido.
--}}
@php
    use App\Http\Controllers\Web\Workshop\InspectionController;
    use App\Http\Controllers\Web\Workshop\MaintenanceController;
    use App\Support\Vehicle\VehicleLookupResult;

    $isInspection = $context === 'inspection';
    $readonly = $readonly ?? false;
    $chassis = $chassis ?? '';
    $chassisOwned = $chassisOwned ?? false;
    $chassisInvalid = $chassisInvalid ?? false;
    $creatingVehicle = $creatingVehicle ?? false;
    $licensePlate = $licensePlate ?? '';
    $showsFullVehicle = $showsFullVehicle ?? false;
    $stepSearchUrl = route($isInspection ? 'workshop.inspections.create' : 'workshop.maintenances.create');
    $vehicleName = $vehicle ? trim($vehicle->brand.' '.$vehicle->model) : null;
    $vehicleMeta = $vehicle ? collect([$vehicle->year, $vehicle->color])->filter(fn ($part) => filled($part))->implode(' · ') : '';
    $matchedPreviousPlate = ($lookup ?? null)?->matchedBy === VehicleLookupResult::MATCH_PREVIOUS_PLATE;
    // Sem acesso ao veículo inteiro, a placa que volta é a que a oficina digitou: ela já a conhece.
    $plateOnCard = $showsFullVehicle ? $vehicle?->license_plate : ($licensePlate !== '' ? $licensePlate : null);
    // Veículo com placa, mas sem placa a devolver: a edição de um laudo cujo emissor não tem
    // vínculo com o veículo. "Não informada" diria que o carro não tem placa, o que é outra coisa.
    $plateWithheld = $plateOnCard === null && ! $showsFullVehicle && filled($vehicle?->license_plate);
    $inviteText = $isInspection
        ? 'Crie sua conta grátis no RevisaLog e adicione o seu veículo para receber o laudo da vistoria e o histórico de manutenção: '.route('register')
        : 'Crie sua conta grátis no RevisaLog e adicione o seu veículo para receber o histórico de manutenção com o Selo da oficina: '.route('register');
@endphp

<x-ui.form-section as="section" id="secao-veiculo" :number="1" title="Veículo"
    :description="($vehicle || $creatingVehicle) ? null : 'Digite a placa (ou o chassi) para conferir o veículo antes de preencher '.($isInspection ? 'o laudo.' : 'a OS.')">
    @if($vehicle)
        <div role="status" class="flex flex-wrap items-center gap-4 rounded-control border border-border bg-surface-muted/60 p-3" data-vehicle-found>
            <x-vehicle-cover :vehicle="$vehicle" variant="thumb" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-foreground">{{ $vehicleName }}</p>
                @if($vehicleMeta !== '')
                    <p class="text-sm text-muted-foreground">{{ $vehicleMeta }}</p>
                @endif
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    @if(filled($plateOnCard))
                        <span class="rounded-md border border-border-strong bg-surface px-1.5 py-px font-mono text-xs tracking-wider text-foreground"><span class="sr-only">Placa </span>{{ $plateOnCard }}</span>
                    @elseif($plateWithheld)
                        <span class="text-muted-foreground" data-vehicle-plate-withheld>Placa não exibida</span>
                    @else
                        <span class="text-muted-foreground">Placa não informada</span>
                    @endif
                    @if($showsFullVehicle && $vehicle->current_kilometers !== null)
                        <span class="text-muted-foreground" data-vehicle-kilometers>Última quilometragem registrada: <span class="font-medium text-foreground tabular-nums">{{ number_format((int) $vehicle->current_kilometers, 0, ',', '.') }} km</span></span>
                    @endif
                </p>
                @if($matchedPreviousPlate && ! $readonly)
                    <p class="mt-1 text-sm text-muted-foreground">
                        @if($showsFullVehicle)
                            A placa {{ $licensePlate }} é antiga: o veículo usa hoje a placa {{ $vehicle->license_plate }}.
                        @else
                            A placa {{ $licensePlate }} é antiga: o veículo usa outra placa hoje.
                        @endif
                    </p>
                @endif
            </div>
            @unless($readonly)
                <x-ui.link :href="$stepSearchUrl" icon="arrow-path">Trocar veículo</x-ui.link>
            @endunless
        </div>
        @if($readonly)
            <p class="text-sm text-muted-foreground">{{ $isInspection ? 'O veículo não muda depois que o laudo é emitido.' : 'O veículo não muda depois que a OS é registrada.' }}</p>
        @elseif(! ($vehicleHasOwner ?? true))
            <x-ui.alert variant="info" title="Veículo sem proprietário no RevisaLog" data-vehicle-ownerless>
                @if($isInspection)
                    Você pode emitir o laudo mesmo assim. Ele fica guardado no histórico do chassi e aparece para o proprietário quando ele vincular o veículo.
                @else
                    Você pode registrar a OS mesmo assim. Notas fiscais e fotos ficam só com a sua oficina até o proprietário aceitar, e depois de salvar você pode avisar o cliente.
                @endif
            </x-ui.alert>
        @endif
    @elseif($creatingVehicle)
        <div role="status" class="rounded-control border border-border bg-surface-muted/60 p-3" data-vehicle-creating>
            <p class="text-sm text-foreground">Chassi <span class="font-mono tracking-wider">{{ $chassis }}</span> ainda não está no RevisaLog. Informe marca, modelo e ano abaixo: criamos o veículo junto com {{ $isInspection ? 'o laudo' : 'a OS' }}, sem placa, RENAVAM nem dados do proprietário.</p>
            <x-ui.link :href="$stepSearchUrl" icon="arrow-path">Trocar veículo</x-ui.link>
        </div>
    @else
        <form method="GET" action="{{ $stepSearchUrl }}" role="search" aria-label="Buscar veículo pela placa" data-plate-search>
            <x-ui.field name="license_plate" label="Placa do veículo" hint="ABC1D23 ou ABC1234, sem traço." required>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <x-ui.input :value="$licensePlate" required maxlength="8" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                data-mask="plate" class="font-mono tracking-wider uppercase sm:max-w-56" />
                    <x-ui.button type="submit" icon="magnifying-glass" loading-label="Buscando…">Buscar veículo</x-ui.button>
                </div>
            </x-ui.field>
        </form>

        <form method="GET" action="{{ $stepSearchUrl }}" role="search" aria-label="Buscar veículo pelo chassi" class="mt-4" data-chassis-search>
            <x-ui.field name="chassis" label="Ou busque pelo chassi" hint="17 caracteres. Se o carro ainda não estiver no RevisaLog, você o cadastra aqui mesmo.">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <x-ui.input :value="$chassis" maxlength="17" autocomplete="off" autocapitalize="characters" spellcheck="false" class="font-mono tracking-wider uppercase sm:max-w-80" />
                    <x-ui.button type="submit" variant="secondary" icon="magnifying-glass" loading-label="Buscando…">Buscar pelo chassi</x-ui.button>
                </div>
            </x-ui.field>
        </form>

        @if($chassisInvalid)
            <x-ui.alert variant="warning" role="status" title="Chassi incompleto" data-chassis-invalid>O chassi tem 17 caracteres. Confira e tente de novo.</x-ui.alert>
        @endif

        @if($chassisOwned)
            <x-ui.alert variant="info" role="status" title="Este chassi já tem proprietário no RevisaLog" data-chassis-owned>
                {{ $isInspection ? InspectionController::chassisOwnedMessage() : MaintenanceController::chassisOwnedMessage() }}
            </x-ui.alert>
        @endif

        @if($licensePlate !== '')
            <div role="status" data-vehicle-not-found>
                <x-ui.empty-state size="sm" icon="truck" heading-level="h3"
                                  :title="'Veículo '.$licensePlate.' ainda não está no RevisaLog'"
                                  :description="'Cadastre o veículo pelo chassi, só com marca, modelo e ano, e '.($isInspection ? 'emita o laudo.' : 'registre a OS.').' O proprietário vincula o carro depois.'">
                    <x-slot:actions>
                        <x-ui.copy-button :value="$inviteText" label="Copiar convite para o cliente" copied-label="Convite copiado" size="md" />
                        <x-ui.button variant="ghost" :href="$stepSearchUrl">Tentar outra placa</x-ui.button>
                    </x-slot:actions>
                    <p>Tem o chassi ou o RENAVAM? <a href="{{ route('vehicle.search') }}" class="link underline">Buscar veículo por outro dado</a>.</p>
                </x-ui.empty-state>
            </div>
        @endif
    @endif
</x-ui.form-section>
