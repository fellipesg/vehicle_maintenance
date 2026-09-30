{{-- Campos da marca (nova e edição). $brand é opcional: sem ele, a marca nasce ativa. --}}
<div class="grid gap-5">
    <x-ui.field name="name" label="Nome da marca" hint="Como aparece nos formulários de veículo. Ex.: Mercedes-Benz" required>
        <x-ui.input :value="$brand->name ?? ''" required maxlength="100" autocomplete="off" />
    </x-ui.field>

    <x-ui.switch
        name="is_active"
        label="Marca ativa"
        description="Marca inativa some dos formulários de veículo, mas continua nos cadastros antigos."
        :checked="(bool) ($brand->is_active ?? true)"
    />
</div>
