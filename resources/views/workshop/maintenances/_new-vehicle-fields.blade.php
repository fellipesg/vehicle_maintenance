{{--
    Dados do veículo que a oficina cria junto com a OS, quando o chassi ainda não está no RevisaLog.
    Fica dentro do formulário principal da Nova OS. O veículo nasce só com chassi, marca, modelo e
    ano (LGPD: sem placa, RENAVAM nem dado do dono); o proprietário o vincula depois.
--}}
<x-ui.form-section id="secao-veiculo-novo" title="Dados do veículo"
    description="Só o que identifica o modelo do carro. Placa e RENAVAM ficam com o proprietário." data-new-vehicle-fields>
    <div class="grid items-start gap-4 sm:grid-cols-3">
        <x-ui.field name="new_vehicle[brand]" label="Marca" required>
            <x-ui.input id="new_vehicle_brand" :value="old('new_vehicle.brand')" required maxlength="100" autocomplete="off" />
        </x-ui.field>
        <x-ui.field name="new_vehicle[model]" label="Modelo" required>
            <x-ui.input :value="old('new_vehicle.model')" required maxlength="100" autocomplete="off" />
        </x-ui.field>
        <x-ui.field name="new_vehicle[year]" label="Ano do modelo" required>
            <x-ui.input type="number" :value="old('new_vehicle.year')" required min="1900" max="{{ (int) date('Y') + 1 }}" inputmode="numeric" />
        </x-ui.field>
    </div>
</x-ui.form-section>
