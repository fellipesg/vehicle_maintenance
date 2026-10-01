{{--
    Campos do perfil da oficina (Cadastrar oficina e Editar oficina), em seções: Identidade,
    Contato, Endereço e Redes sociais. Telefone, WhatsApp e CEP usam a máscara de dígitos
    (resources/js/form-ux.js); o servidor também tira pontos e traços antes de validar, então
    "01310-100" vale. UF é uma lista. Redes aceitam "@perfil" ou o link.

    Variável: $workshop (na edição).
--}}
@php
    use App\Http\Controllers\Web\Workshop\ProfileController;

    $workshop = $workshop ?? null;
    $stateOptions = collect(ProfileController::STATES)->mapWithKeys(fn (string $name, string $uf) => [$uf => "{$name} ({$uf})"])->all();
@endphp

<div class="space-y-6">
    <x-ui.form-section id="secao-identidade" title="Identidade" description="Nome e logo aparecem no Selo da oficina, no PDF do histórico e no diretório.">
        <x-ui.field name="name" label="Nome da oficina" required>
            <x-ui.input :value="$workshop?->name" required maxlength="255" autocomplete="organization" />
        </x-ui.field>

        <x-ui.field name="logo" label="Logo da oficina" optional
                    hint="PNG quadrado com fundo transparente fica melhor: a logo aparece num círculo no Selo da oficina e num retângulo no PDF.">
            <x-ui.file-input accept="image/jpeg,image/png,image/webp" :max-mb="2" />
        </x-ui.field>

        @if($workshop?->logoUrl())
            <div class="flex flex-wrap items-end gap-6 rounded-control border border-border bg-surface-muted/60 p-4" data-logo-preview>
                <figure class="flex flex-col items-center gap-2">
                    <img src="{{ $workshop->logoUrl() }}" alt="" class="size-16 rounded-full bg-surface object-contain p-1.5 ring-1 ring-border">
                    <figcaption class="text-xs text-muted-foreground">No selo</figcaption>
                </figure>
                <figure class="flex flex-col items-center gap-2">
                    <img src="{{ $workshop->logoUrl() }}" alt="" class="h-16 w-auto max-w-40 rounded-control border border-border bg-surface object-contain p-1">
                    <figcaption class="text-xs text-muted-foreground">No PDF</figcaption>
                </figure>
                <p class="min-w-0 flex-1 text-sm text-muted-foreground">Logo atual da {{ $workshop->name }}. Envie outra acima para trocar.</p>
            </div>
        @endif
    </x-ui.form-section>

    <x-ui.form-section id="secao-contato" title="Contato" description="O cliente liga ou chama no WhatsApp a partir do diretório e da OS.">
        <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
            <x-ui.field name="phone" label="Telefone" hint="Com DDD, só números." required>
                <x-ui.input type="tel" :value="$workshop?->phone" required autocomplete="tel-national" inputmode="numeric"
                            data-mask="digits" data-min-digits="10" data-max-digits="11" placeholder="11999999999" />
            </x-ui.field>
            <x-ui.field name="whatsapp" label="WhatsApp" hint="Se ficar vazio, usamos o telefone." optional>
                <x-ui.input type="tel" :value="$workshop?->whatsapp" autocomplete="off" inputmode="numeric"
                            data-mask="digits" data-min-digits="10" data-max-digits="11" placeholder="11999999999" />
            </x-ui.field>
        </div>
        <x-ui.field name="email" label="E-mail" optional>
            <x-ui.input type="email" :value="$workshop?->email" maxlength="255" autocomplete="email" />
        </x-ui.field>
    </x-ui.form-section>

    <x-ui.form-section id="secao-endereco" title="Endereço" description="Usado no diretório de oficinas e no mapa.">
        <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
            <x-ui.field name="cep" label="CEP" hint="8 dígitos." required>
                <x-ui.input :value="$workshop?->cep" required autocomplete="postal-code" inputmode="numeric"
                            data-mask="digits" data-min-digits="8" data-max-digits="8" placeholder="01310100" class="tabular-nums" />
            </x-ui.field>
            <x-ui.field name="street" label="Rua" required class="sm:col-span-2">
                <x-ui.input :value="$workshop?->street" required maxlength="255" autocomplete="address-line1" />
            </x-ui.field>
        </div>
        <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
            <x-ui.field name="number" label="Número" required>
                <x-ui.input :value="$workshop?->number" required maxlength="20" autocomplete="off" />
            </x-ui.field>
            <x-ui.field name="complement" label="Complemento" optional class="sm:col-span-2">
                <x-ui.input :value="$workshop?->complement" maxlength="255" autocomplete="address-line2" />
            </x-ui.field>
        </div>
        <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
            <x-ui.field name="neighborhood" label="Bairro" required>
                <x-ui.input :value="$workshop?->neighborhood" required maxlength="255" autocomplete="address-level3" />
            </x-ui.field>
            <x-ui.field name="city" label="Cidade" required>
                <x-ui.input :value="$workshop?->city" required maxlength="255" autocomplete="address-level2" />
            </x-ui.field>
            <x-ui.field name="state" label="UF" required>
                <x-ui.select :options="$stateOptions" :value="$workshop?->state" placeholder="Selecione a UF" required autocomplete="address-level1" />
            </x-ui.field>
        </div>
    </x-ui.form-section>

    <x-ui.form-section id="secao-redes" title="Redes sociais" description="Aparecem no perfil da oficina. Use o @ do perfil ou o link completo.">
        <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
            <x-ui.field name="instagram" label="Instagram" optional>
                <x-ui.input :value="$workshop?->instagram" maxlength="255" autocomplete="off" spellcheck="false" placeholder="@suaoficina" leading-icon="globe-alt" />
            </x-ui.field>
            <x-ui.field name="facebook" label="Facebook" optional>
                <x-ui.input :value="$workshop?->facebook" maxlength="255" autocomplete="off" spellcheck="false" placeholder="@suaoficina" leading-icon="globe-alt" />
            </x-ui.field>
        </div>
    </x-ui.form-section>
</div>
