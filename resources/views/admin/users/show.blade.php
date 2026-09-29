@extends('layouts.admin')

@section('title', $user->name)

@php
    use App\Enums\Portal;

    $adminBreadcrumbs = [['Cadastros'], ['Usuários', route('admin.users.index')], [$user->name]];
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
    $isWorkshopAccount = $portal === Portal::Workshop;
    $isDealerAccount = $portal === Portal::Dealer;
    $vehiclesLabel = $isDealerAccount ? 'Veículos no estoque' : 'Veículos atuais';
    $currentVehicleCount = $currentVehicles->count();
    $launchedListUrl = route('admin.maintenances.index', ['usuario' => $user->id]);
    $userLocation = collect([$user->city, $user->state])->filter(fn (mixed $part): bool => filled($part))->implode('/');
    $workshopAddress = $workshop
        ? collect([
            collect([$workshop->street, $workshop->number])->filter(fn (mixed $part): bool => filled($part))->implode(', '),
            $workshop->neighborhood,
            collect([$workshop->city, $workshop->state])->filter(fn (mixed $part): bool => filled($part))->implode('/'),
        ])->filter(fn (mixed $part): bool => filled($part))->implode(' — ')
        : '';
    $workshopIsOnMap = $workshop && filled($workshop->latitude) && filled($workshop->longitude);
    $vehiclesTab = ['id' => 'veiculos', 'label' => $vehiclesLabel, 'count' => $currentVehicleCount, 'icon' => 'truck'];
    $maintenancesTab = ['id' => 'manutencoes-lancadas', 'label' => 'Manutenções lançadas', 'count' => $launchedMaintenanceCount, 'icon' => 'wrench-screwdriver'];
    // A oficina trabalha com o que lançou; proprietário e lojista, com os veículos.
    $userTabs = $isWorkshopAccount ? [$maintenancesTab, $vehiclesTab] : [$vehiclesTab, $maintenancesTab];
@endphp

@section('content')
    <x-ui.page-header :title="$user->name" :description="$user->email">
        <div class="flex flex-wrap items-center gap-2">
            <x-admin.user-type-badge :user="$user" />
            @if($user->is_admin)
                <x-ui.badge variant="info" icon="shield-check">Administrador</x-ui.badge>
            @endif
        </div>
        <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4" data-slot="user-account-details">
            <div class="min-w-0">
                <dt class="text-muted-foreground">E-mail</dt>
                <dd class="truncate text-foreground"><x-ui.link :href="'mailto:'.$user->email" variant="inline">{{ $user->email }}</x-ui.link></dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Telefone</dt>
                <dd class="text-foreground">
                    @if(filled($user->phone))
                        <x-ui.link :href="'tel:'.preg_replace('/\D+/', '', (string) $user->phone)" variant="inline">{{ $user->phone }}</x-ui.link>
                    @else
                        Não informado
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Cidade</dt>
                <dd class="text-foreground">{{ $userLocation !== '' ? $userLocation : 'Não informada' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Cadastro</dt>
                <dd class="text-foreground tabular-nums">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</dd>
            </div>
        </dl>

        @if($launchedMaintenanceCount > 0)
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="wrench-screwdriver" :href="$launchedListUrl">Ver manutenções lançadas</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="space-y-8">
        <section aria-labelledby="metricas-titulo">
            <h2 id="metricas-titulo" class="sr-only">Números da conta</h2>
            <div class="grid gap-3 sm:grid-cols-3" data-slot="user-metrics">
                @if($isWorkshopAccount)
                    <x-ui.stat label="Manutenções lançadas por esta conta" :value="$formatCount($launchedMaintenanceCount)" icon="wrench-screwdriver" hint="Ordens de serviço registradas pela oficina." />
                    <x-ui.stat label="Com Selo da oficina" :value="$formatCount($workshopSealedCount)" icon="shield-check" hint="Manutenções que a oficina vinculada verificou." />
                    <x-ui.stat label="Localização" :value="$workshop ? ($workshopIsOnMap ? 'No mapa' : 'Sem coordenadas') : 'Sem oficina'" icon="map-pin" :hint="$workshop ? ($workshopIsOnMap ? 'Aparece no mapa e na busca por proximidade.' : 'Confira o endereço da oficina.') : 'A conta ainda não cadastrou a oficina.'" />
                @else
                    <x-ui.stat :label="$vehiclesLabel" :value="$formatCount($currentVehicleCount)" icon="truck" :hint="$isDealerAccount ? 'Próprios e em consignação.' : 'Veículos de que a conta é a proprietária hoje.'" />
                    <x-ui.stat label="Manutenções nos veículos atuais" :value="$formatCount((int) $currentVehicleMaintenanceCount)" icon="clock" hint="Histórico completo, com registros de donos anteriores." />
                    <x-ui.stat label="Manutenções lançadas por esta conta" :value="$formatCount($launchedMaintenanceCount)" icon="wrench-screwdriver" hint="O que esta conta registrou, em qualquer veículo." />
                @endif
            </div>
        </section>

        @if($isWorkshopAccount)
            <x-ui.section id="oficina-vinculada" title="Oficina vinculada" description="Dados que aparecem no Selo da oficina e no mapa.">
                @if($workshop)
                    @if($workshopIsOnMap)
                        <x-slot:actions>
                            <x-ui.link :href="route('admin.maps.workshops')" icon="map">Ver no mapa de oficinas</x-ui.link>
                        </x-slot:actions>
                    @endif
                    <x-ui.card>
                        <div class="flex items-start gap-4">
                            <x-ui.avatar :name="$workshop->name" :src="$workshop->logoUrl()" shape="square" size="lg" />
                            <dl class="grid min-w-0 flex-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                <div class="min-w-0 sm:col-span-2">
                                    <dt class="text-muted-foreground">Nome</dt>
                                    <dd class="font-semibold text-foreground">{{ $workshop->name }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-muted-foreground">Endereço</dt>
                                    <dd class="text-foreground">{{ $workshopAddress !== '' ? $workshopAddress : 'Não informado' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Localização</dt>
                                    <dd>
                                        @if($workshopIsOnMap)
                                            <x-ui.badge variant="success" icon="map-pin">No mapa</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="warning" icon="exclamation-triangle">Sem coordenadas</x-ui.badge>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </x-ui.card>
                @else
                    <x-ui.empty-state icon="building-storefront" title="Oficina ainda não cadastrada" description="A conta existe, mas ainda não completou o cadastro da oficina no portal Oficina." />
                @endif
            </x-ui.section>
        @endif

        <x-ui.tabs label="Conteúdo da conta" variant="line" sync-url>
            <x-slot:tabs>
                @foreach($userTabs as $tabIndex => $userTab)
                    <x-ui.tab :target="$userTab['id']" :active="$tabIndex === 0" :icon="$userTab['icon']" :badge="$userTab['count']" :badge-label="mb_strtolower($userTab['label'])">{{ $userTab['label'] }}</x-ui.tab>
                @endforeach
            </x-slot:tabs>

            @foreach($userTabs as $tabIndex => $userTab)
                <x-ui.tab-panel :id="$userTab['id']" :active="$tabIndex === 0">
                    @if($userTab['id'] === 'veiculos')
                        <x-ui.section id="veiculos-da-conta" :title="$vehiclesLabel" :description="$isDealerAccount ? 'Estoque da loja, com a procedência do histórico de cada veículo.' : 'Veículos de que a conta é a proprietária hoje, com a procedência do histórico.'">
                            @if($currentVehicles->isEmpty())
                                <x-ui.empty-state icon="truck" :title="$isDealerAccount ? 'Estoque vazio' : 'Nenhum veículo atual'" :description="$isDealerAccount ? 'A loja ainda não adicionou veículos ao estoque.' : 'Esta conta não é proprietária de nenhum veículo hoje.'" />
                            @else
                                <ul role="list" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-slot="user-vehicles">
                                    @foreach($currentVehicles as $vehicle)
                                        <x-vehicle.card :vehicle="$vehicle" :href="route('admin.vehicles.show', $vehicle)" as="li" heading-level="h4">
                                            @if($vehicle->pivot?->ownership_type === 'consignment')
                                                <x-slot:badges>
                                                    <x-ui.badge variant="info" size="sm">Consignação</x-ui.badge>
                                                </x-slot:badges>
                                            @endif
                                        </x-vehicle.card>
                                    @endforeach
                                </ul>
                            @endif
                        </x-ui.section>
                    @else
                        <x-ui.section id="manutencoes-da-conta" title="Manutenções lançadas por esta conta" :description="$launchedMaintenanceCount > $recentLimit ? 'As '.$recentLimit.' mais recentes, pela data do serviço.' : 'Pela data do serviço, da mais recente para a mais antiga.'">
                            @if($launchedMaintenanceCount > $recentLimit)
                                <x-slot:actions>
                                    <x-ui.link :href="$launchedListUrl" arrow>Ver todas as {{ $formatCount($launchedMaintenanceCount) }}</x-ui.link>
                                </x-slot:actions>
                            @endif

                            @if($recentLaunchedMaintenances->isEmpty())
                                <x-ui.empty-state
                                    icon="wrench-screwdriver"
                                    title="Nenhuma manutenção lançada"
                                    :description="$isWorkshopAccount ? 'As ordens de serviço da oficina aparecem aqui, com o Selo da oficina.' : 'O que esta conta registrar aparece aqui, com a procedência.'"
                                />
                            @else
                                <x-provenance-legend class="mb-4" />

                                {{-- Cards de procedência com o que o suporte precisa ler: a oficina informada nas declaradas e a descrição. --}}
                                <ol role="list" class="space-y-3" aria-label="Manutenções lançadas por esta conta, da mais recente para a mais antiga" data-slot="launched-maintenances">
                                    @foreach($recentLaunchedMaintenances as $maintenance)
                                        @php
                                            $informedWorkshop = $maintenance->isVerified() ? null : $maintenance->displayWorkshopName();
                                        @endphp
                                        <li>
                                            <x-provenance-card
                                                :maintenance="$maintenance"
                                                as="article"
                                                heading-level="h4"
                                                show-vehicle
                                                :vehicle-href="$maintenance->vehicle ? route('admin.vehicles.show', $maintenance->vehicle) : null"
                                                :anchor="false"
                                            />
                                            @if(filled($informedWorkshop) || filled($maintenance->description))
                                                <div class="mt-2 space-y-1 px-1 text-sm text-muted-foreground">
                                                    @if(filled($informedWorkshop))
                                                        <p>Oficina informada: <span class="text-foreground">{{ $informedWorkshop }}</span></p>
                                                    @endif
                                                    @if(filled($maintenance->description))
                                                        <p class="whitespace-pre-line">{{ \Illuminate\Support\Str::limit($maintenance->description, 300) }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </x-ui.section>
                    @endif
                </x-ui.tab-panel>
            @endforeach
        </x-ui.tabs>
    </div>
@endsection
