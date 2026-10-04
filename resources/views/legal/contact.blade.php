@extends('layouts.app')

@section('title', 'Contato')

@push('head')
    <meta name="description" content="Fale com o suporte do RevisaLog: dúvidas, ajuda com o histórico do veículo, parcerias com oficinas e lojistas e pedidos sobre dados pessoais (LGPD).">
    @if($turnstileSiteKey)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header eyebrow="Suporte" title="Contato">
        <x-slot:description>
            Escreva para <x-ui.link :href="'mailto:'.$supportEmail" variant="inline">{{ $supportEmail }}</x-ui.link> ou envie a mensagem abaixo. Respondemos por e-mail.
        </x-slot:description>
    </x-ui.page-header>

    <x-ui.card padding="lg">
        <form method="POST" action="{{ route('contact.store') }}" class="relative grid gap-5" data-slot="contact-form">
            @csrf

            @if(filled($ref))
                <input type="hidden" name="ref" value="{{ $ref }}">
            @endif

            <x-ui.form-errors :ids="['cf-turnstile-response' => null]" />

            {{-- Armadilha para robôs: fora da tela, sem foco e escondida do leitor de tela. --}}
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            <x-ui.field name="name" label="Nome" required>
                <x-ui.input :value="auth()->user()?->name" required maxlength="120" autocomplete="name" />
            </x-ui.field>

            <x-ui.field name="email" label="E-mail" hint="A resposta chega neste endereço." required>
                <x-ui.input type="email" :value="auth()->user()?->email" required maxlength="255" autocomplete="email" inputmode="email" />
            </x-ui.field>

            <x-ui.field name="subject" label="Assunto" required>
                <x-ui.select :options="$subjects" :value="request('assunto')" placeholder="Selecione" required />
            </x-ui.field>

            <x-ui.field name="message" label="Mensagem" hint="Se for sobre um veículo, informe a placa ou o chassi." required>
                <x-ui.textarea rows="6" maxlength="4000" autosize counter required />
            </x-ui.field>

            @if($turnstileSiteKey)
                <div class="grid gap-1.5">
                    <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-language="pt-br"></div>
                    @error('cf-turnstile-response')
                        <p class="flex items-start gap-1.5 text-sm text-danger" data-slot="field-error">
                            <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
                            <span><span class="sr-only">Erro: </span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>
            @endif

            <p class="text-sm text-muted-foreground">
                Usamos seus dados só para responder esta mensagem. Veja a
                <x-ui.link :href="route('legal.privacy')" variant="inline">Política de privacidade</x-ui.link>.
            </p>

            <div class="flex justify-end border-t border-border pt-5 max-sm:*:grow" data-slot="form-actions">
                <x-ui.button type="submit" icon="envelope" loading-label="Enviando…">Enviar mensagem</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-ui.container>
@endsection
