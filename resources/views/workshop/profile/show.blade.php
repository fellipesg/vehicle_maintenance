{{--
    Minha oficina: como a oficina aparece para o cliente. Identidade (logo inteira, nome e cidade),
    prévia do Selo da oficina, contato com tel:, wa.me e mailto:, endereço com CEP formatado, redes
    e o que falta para o perfil ficar completo. Campos vazios não aparecem (viram item da lista do
    que falta).
--}}
@extends('layouts.app')

@section('title', 'Minha oficina')

@php
    $digits = fn (?string $value): string => preg_replace('/\D/', '', (string) $value) ?? '';
    $formatPhone = function (?string $value) use ($digits): ?string {
        $number = $digits($value);

        if (str_starts_with($number, '55') && strlen($number) > 11) {
            $number = substr($number, 2);
        }

        return match (strlen($number)) {
            11 => sprintf('(%s) %s-%s', substr($number, 0, 2), substr($number, 2, 5), substr($number, 7)),
            10 => sprintf('(%s) %s-%s', substr($number, 0, 2), substr($number, 2, 4), substr($number, 6)),
            0 => null,
            default => $value,
        };
    };
    $whatsappLink = function (?string $value) use ($digits): ?string {
        $number = $digits($value);

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.(str_starts_with($number, '55') && strlen($number) > 11 ? $number : '55'.$number);
    };
    $socialLabel = fn (?string $url): ?string => filled($url) ? (preg_replace('#^https?://(www\.)?#i', '', rtrim((string) $url, '/')) ?? $url) : null;
@endphp

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header title="Minha oficina"
                          description="Como a sua oficina aparece para os clientes: no Selo da oficina, no PDF do histórico e no diretório.">
            @if($workshop)
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="pencil-square" :href="route('workshop.profile.edit')">Editar oficina</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if(! $workshop)
            <x-ui.empty-state icon="building-storefront" heading-level="h2" title="Oficina ainda não cadastrada"
                              description="Cadastre o nome, o contato e o endereço. Eles aparecem no Selo da oficina de cada OS e no diretório.">
                <x-slot:actions>
                    <x-ui.button :href="route('workshop.profile.create')">Cadastrar oficina</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            @php
                $phone = $formatPhone($workshop->phone);
                $whatsapp = $formatPhone($workshop->whatsapp);
                $checklist = [
                    ['done' => $workshop->logoUrl() !== null, 'label' => 'Logo da oficina'],
                    ['done' => filled($workshop->whatsapp), 'label' => 'WhatsApp para os clientes'],
                    ['done' => filled($workshop->email), 'label' => 'E-mail de contato'],
                    ['done' => filled($workshop->instagram) || filled($workshop->facebook), 'label' => 'Instagram ou Facebook'],
                    ['done' => $hasActiveTemplate, 'label' => 'Modelo de garantia ativo', 'url' => route('workshop.warranty-templates.create')],
                ];
                $checklistDone = collect($checklist)->where('done', true)->count();
                $checklistTotal = count($checklist);
                $cityLine = collect([$workshop->city, $workshop->state])->filter()->implode('/');
            @endphp

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-6">
                    <x-ui.card as="section" title="Identidade" heading-level="h2" data-profile-identity>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            @if($workshop->logoUrl())
                                <img src="{{ $workshop->logoUrl() }}" alt="Logo da {{ $workshop->name }}" class="h-24 w-auto max-w-48 rounded-control border border-border bg-surface object-contain p-2">
                            @else
                                <div class="flex h-24 w-32 shrink-0 flex-col items-center justify-center gap-1 rounded-control border border-dashed border-border-strong text-center text-xs text-muted-foreground">
                                    <x-ui.icon name="photo" class="size-6" />
                                    Sem logo
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xl font-semibold text-foreground">{{ $workshop->name }}</p>
                                @if($cityLine !== '')
                                    <p class="text-sm text-muted-foreground">{{ $cityLine }}</p>
                                @endif
                                @unless($workshop->logoUrl())
                                    <x-ui.link :href="route('workshop.profile.edit')" class="mt-2 inline-flex text-sm" icon="arrow-up-tray">Enviar logo</x-ui.link>
                                @endunless
                            </div>
                        </div>
                    </x-ui.card>

                    <x-ui.card as="section" title="Contato" heading-level="h2" data-profile-contact>
                        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            @if($phone)
                                <div>
                                    <dt class="text-muted-foreground">Telefone</dt>
                                    <dd class="mt-0.5"><a href="tel:+55{{ $digits($workshop->phone) }}" class="link tabular-nums">{{ $phone }}</a></dd>
                                </div>
                            @endif
                            @if($whatsapp)
                                <div>
                                    <dt class="text-muted-foreground">WhatsApp</dt>
                                    <dd class="mt-0.5"><x-ui.link :href="$whatsappLink($workshop->whatsapp)" external class="tabular-nums">{{ $whatsapp }}</x-ui.link></dd>
                                </div>
                            @endif
                            @if(filled($workshop->email))
                                <div class="sm:col-span-2">
                                    <dt class="text-muted-foreground">E-mail</dt>
                                    <dd class="mt-0.5 break-all"><a href="mailto:{{ $workshop->email }}" class="link">{{ $workshop->email }}</a></dd>
                                </div>
                            @endif
                        </dl>
                    </x-ui.card>

                    <x-ui.card as="section" title="Endereço" heading-level="h2" data-profile-address>
                        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground">Rua e número</dt>
                                <dd class="mt-0.5 text-foreground">{{ $workshop->street }}, {{ $workshop->number }}@if(filled($workshop->complement)) · {{ $workshop->complement }}@endif</dd>
                            </div>
                            @if(filled($workshop->neighborhood))
                                <div>
                                    <dt class="text-muted-foreground">Bairro</dt>
                                    <dd class="mt-0.5 text-foreground">{{ $workshop->neighborhood }}</dd>
                                </div>
                            @endif
                            @if($cityLine !== '')
                                <div>
                                    <dt class="text-muted-foreground">Cidade</dt>
                                    <dd class="mt-0.5 text-foreground">{{ $cityLine }}</dd>
                                </div>
                            @endif
                            @if(filled($workshop->cep))
                                <div>
                                    <dt class="text-muted-foreground">CEP</dt>
                                    <dd class="mt-0.5 text-foreground tabular-nums">{{ $workshop->formatted_cep }}</dd>
                                </div>
                            @endif
                        </dl>
                    </x-ui.card>

                    @if(filled($workshop->instagram) || filled($workshop->facebook))
                        <x-ui.card as="section" title="Redes sociais" heading-level="h2" data-profile-social>
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                @if(filled($workshop->instagram))
                                    <div class="min-w-0">
                                        <dt class="text-muted-foreground">Instagram</dt>
                                        <dd class="mt-0.5 break-all"><x-ui.link :href="$workshop->instagram" external>{{ $socialLabel($workshop->instagram) }}</x-ui.link></dd>
                                    </div>
                                @endif
                                @if(filled($workshop->facebook))
                                    <div class="min-w-0">
                                        <dt class="text-muted-foreground">Facebook</dt>
                                        <dd class="mt-0.5 break-all"><x-ui.link :href="$workshop->facebook" external>{{ $socialLabel($workshop->facebook) }}</x-ui.link></dd>
                                    </div>
                                @endif
                            </dl>
                        </x-ui.card>
                    @endif
                </div>

                <div class="space-y-6">
                    <x-ui.card as="section" title="Prévia do Selo da oficina" heading-level="h2"
                               description="Assim o selo aparece na OS, para o cliente e na página de verificação." data-seal-preview>
                        <div class="prov-seal prov-verified" aria-hidden="true">
                            <div class="flex items-center gap-3">
                                @if($workshop->logoUrl())
                                    <img src="{{ $workshop->logoUrl() }}" alt="" class="size-14 shrink-0 rounded-full bg-surface object-contain p-1.5 ring-1 ring-border">
                                @else
                                    <x-provenance-marker size="lg" :event="['is_verified' => true, 'workshop_name' => $workshop->name]" />
                                @endif
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold tracking-wide text-[color:var(--prov-ink)] uppercase">Selo da oficina</p>
                                    <p class="truncate font-semibold text-foreground">{{ $workshop->name }}</p>
                                    <p class="text-xs text-muted-foreground">Emitido em {{ now()->format('d/m/Y') }} · <span class="font-mono">RVL-XXXX-XX</span></p>
                                </div>
                            </div>
                        </div>
                        <p class="sr-only">Prévia: Selo da oficina, {{ $workshop->name }}, com a data de emissão e o código de verificação.</p>
                    </x-ui.card>

                    <x-ui.card as="section" title="Perfil completo" heading-level="h2" data-profile-checklist>
                        <p class="text-sm text-muted-foreground"><span class="font-semibold text-foreground tabular-nums">{{ $checklistDone }} de {{ $checklistTotal }}</span> itens preenchidos</p>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                            <div class="h-full rounded-full bg-primary" style="width: {{ (int) round($checklistDone / max(1, $checklistTotal) * 100) }}%"></div>
                        </div>
                        <ul role="list" class="mt-4 space-y-2 text-sm">
                            @foreach($checklist as $check)
                                <li class="flex items-start gap-2">
                                    @if($check['done'])
                                        <x-ui.icon name="check-circle" variant="solid" class="mt-0.5 size-5 text-success" />
                                        <span class="text-foreground">{{ $check['label'] }}<span class="sr-only">: preenchido</span></span>
                                    @else
                                        <x-ui.icon name="x-circle" class="mt-0.5 size-5 text-muted-foreground" />
                                        <span class="flex flex-wrap items-baseline gap-x-2 text-foreground">
                                            <span>{{ $check['label'] }}<span class="sr-only">: falta</span></span>
                                            <a href="{{ $check['url'] ?? route('workshop.profile.edit') }}" class="link">Adicionar<span class="sr-only"> {{ mb_strtolower($check['label']) }}</span></a>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </div>
            </div>
        @endif
    </x-ui.container>
@endsection
