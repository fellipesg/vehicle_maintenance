{{--
    Campos da oficina (nome fantasia, CNPJ, contato e endereço), compartilhados pelas duas telas que
    criam uma: o cadastro da landing (workshops.signup) e a última etapa do cadastro em etapas
    (auth.signup.details).

    Par do App\Http\Requests\Concerns\ValidatesWorkshopProfile, que guarda a validação dos mesmos
    campos. Campo novo entra aqui e lá — em duas cópias, a mesma oficina passaria numa tela e não na
    outra.

    Variáveis:
    - $defaults: ['trade_name' => ?string, 'cnpj' => ?string] vindos da prospecção; opcional.
    - $stateOptions: UFs para o <select>.
--}}
@php
    $workshopDefaults = ($defaults ?? []) + ['trade_name' => null, 'cnpj' => null];
@endphp

<fieldset class="space-y-4">
    <legend class="text-base font-semibold text-foreground">A oficina</legend>

    <x-ui.field name="trade_name" label="Nome fantasia" required>
        <x-ui.input :value="$workshopDefaults['trade_name']" required maxlength="255" autocomplete="organization" />
    </x-ui.field>

    <x-ui.field name="cnpj" label="CNPJ" hint="Com ou sem pontuação." required>
        <x-ui.input :value="$workshopDefaults['cnpj']" required maxlength="18" autocomplete="off" inputmode="numeric" data-mask="cnpj" placeholder="00.000.000/0000-00" />
    </x-ui.field>

    <x-ui.field name="phone" label="WhatsApp ou telefone" hint="Com DDD, só números." required>
        <x-ui.input type="tel" required autocomplete="tel-national" inputmode="numeric" data-mask="digits" data-min-digits="10" data-max-digits="11" placeholder="11999999999" />
    </x-ui.field>
</fieldset>

<fieldset class="space-y-4">
    <legend class="text-base font-semibold text-foreground">Endereço</legend>

    <x-ui.field name="cep" label="CEP" hint="8 dígitos. Preenchemos o resto do endereço para você." required>
        <x-ui.input required autocomplete="postal-code" inputmode="numeric" data-mask="digits" data-min-digits="8" data-max-digits="8" data-cep-autofill placeholder="01310100" class="tabular-nums" />
    </x-ui.field>

    <x-ui.field name="street" label="Rua" required>
        <x-ui.input required maxlength="255" autocomplete="address-line1" />
    </x-ui.field>

    <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
        <x-ui.field name="number" label="Número" required>
            <x-ui.input required maxlength="20" autocomplete="off" />
        </x-ui.field>
        <x-ui.field name="complement" label="Complemento" optional>
            <x-ui.input maxlength="255" autocomplete="address-line2" />
        </x-ui.field>
    </div>

    <x-ui.field name="neighborhood" label="Bairro" required>
        <x-ui.input required maxlength="255" autocomplete="address-level3" />
    </x-ui.field>

    <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
        <x-ui.field name="city" label="Cidade" required>
            <x-ui.input required maxlength="255" autocomplete="address-level2" />
        </x-ui.field>
        <x-ui.field name="state" label="UF" required>
            <x-ui.select :options="$stateOptions" placeholder="Selecione a UF" required autocomplete="address-level1" />
        </x-ui.field>
    </div>
</fieldset>
