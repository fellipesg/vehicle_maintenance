@php($locked = $consignment['required'])
<div
    class="rounded-lg border border-automotive-200 bg-automotive-50 px-4 py-4"
    data-consignment-block
>
    <label class="flex items-start gap-3">
        <input
            type="checkbox"
            name="is_consignment"
            value="1"
            class="mt-1 h-5 w-5 rounded border-automotive-300 text-wrench-600"
            data-consignment-toggle
            @checked(old('is_consignment', $locked))
            @disabled($locked)
        >
        <span>
            <span class="block font-semibold text-automotive-800">Veículo em consignação</span>
            <span class="block text-sm text-automotive-600">
                O veículo pertence a outra pessoa e está na garagem para venda.
            </span>
        </span>
    </label>

    @if($locked)
        {{-- The CRLV-e is in someone else's name, so the switch cannot be turned off. --}}
        <input type="hidden" name="is_consignment" value="1">
        <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            O CRLV-e está em nome de outra pessoa, então este veículo entra obrigatoriamente como consignação.
        </p>
    @endif

    <div class="mt-4 space-y-4 @if(! $locked) hidden @endif" data-consignment-panel>
        <div>
            <label for="consignment_owner_name" class="form-label">Nome do proprietário *</label>
            <input
                type="text"
                name="consignment_owner_name"
                id="consignment_owner_name"
                class="form-input"
                value="{{ old('consignment_owner_name', $consignment['owner_name']) }}"
                maxlength="255"
            >
            @error('consignment_owner_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        @if($consignment['contact_locked'])
            <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900">
                <p class="font-medium">Este proprietário já tem conta no Revisalog.</p>
                <p class="mt-1">
                    Vamos avisá-lo automaticamente pela conta dele
                    @if($consignment['masked_email'])
                        ({{ $consignment['masked_email'] }}@if($consignment['masked_phone']) · {{ $consignment['masked_phone'] }}@endif)
                    @endif.
                    Por privacidade, não exibimos o contato completo.
                </p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="consignment_owner_email" class="form-label">E-mail do proprietário</label>
                    <input
                        type="email"
                        name="consignment_owner_email"
                        id="consignment_owner_email"
                        class="form-input"
                        value="{{ old('consignment_owner_email') }}"
                        maxlength="255"
                    >
                    @error('consignment_owner_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="consignment_owner_phone" class="form-label">Telefone do proprietário</label>
                    <input
                        type="text"
                        name="consignment_owner_phone"
                        id="consignment_owner_phone"
                        class="form-input"
                        value="{{ old('consignment_owner_phone') }}"
                        maxlength="30"
                    >
                    @error('consignment_owner_phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-sm text-automotive-500">Informe e-mail ou telefone para avisarmos o proprietário sobre as manutenções registradas.</p>
        @endif

        <div>
            <label for="power_of_attorney" class="form-label">Procuração do proprietário (PDF, opcional)</label>
            <input
                type="file"
                name="power_of_attorney"
                id="power_of_attorney"
                accept="application/pdf,.pdf"
                class="form-input"
            >
            <p class="mt-1 text-sm text-automotive-500">
                {{ $historyHint ?? 'Anexe a procuração para solicitar acesso ao histórico de manutenções já registrado neste veículo. Sem ela, você registra manutenções normalmente, mas não vê o histórico anterior.' }}
            </p>
            @error('power_of_attorney')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-start gap-3">
            <input
                type="checkbox"
                name="consignment_declaration"
                value="1"
                class="mt-1 h-5 w-5 rounded border-automotive-300 text-wrench-600"
                @checked(old('consignment_declaration'))
            >
            <span class="text-sm text-automotive-700">
                Declaro que tenho autorização do proprietário para registrar manutenções neste veículo. *
            </span>
        </label>
        @error('consignment_declaration')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-consignment-block]').forEach((block) => {
        const toggle = block.querySelector('[data-consignment-toggle]');
        const panel = block.querySelector('[data-consignment-panel]');

        if (! toggle || ! panel || toggle.disabled) {
            return;
        }

        toggle.addEventListener('change', () => {
            panel.classList.toggle('hidden', ! toggle.checked);
        });
    });
</script>
@endpush
