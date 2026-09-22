@extends('layouts.app')

@section('title', 'Fale conosco')

@if($turnstileSiteKey)
    @push('head')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif

@section('content')
<div class="mx-auto grid max-w-5xl gap-10 px-4 py-12 lg:grid-cols-[1fr_18rem]">
    <div>
        <h1 class="text-3xl font-bold text-automotive-900">Fale conosco</h1>
        <p class="mt-2 text-automotive-600">Dúvidas, suporte ou parcerias com oficinas e lojistas. Respondemos por e-mail.</p>

        <form method="POST" action="{{ route('contact.store') }}" class="card mt-8 space-y-5" novalidate>
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="contact-name" class="form-label">Nome</label>
                    <input id="contact-name" type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="120" autocomplete="name" class="form-input">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contact-email" class="form-label">E-mail</label>
                    <input id="contact-email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required maxlength="190" autocomplete="email" class="form-input">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="contact-subject" class="form-label">Assunto</label>
                <select id="contact-subject" name="subject" required class="form-select">
                    <option value="" disabled @selected(! old('subject'))>Selecione</option>
                    @foreach($subjects as $value => $label)
                        <option value="{{ $value }}" @selected(old('subject', request('assunto')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="contact-message" class="form-label">Mensagem</label>
                <textarea id="contact-message" name="message" rows="6" required minlength="10" maxlength="5000" class="form-input">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Honeypot: invisível para pessoas, bots costumam preencher. --}}
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="contact-website">Não preencha</label>
                <input id="contact-website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            @if($turnstileSiteKey)
                <div>
                    <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-language="pt-br"></div>
                    @error('cf-turnstile-response')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif

            <p class="text-xs text-automotive-500">
                Usamos seus dados só para responder esta mensagem. Veja a
                <a href="{{ route('legal.privacy') }}" class="underline hover:text-wrench-700">Política de Privacidade</a>.
            </p>

            <button type="submit" class="btn-primary">Enviar mensagem</button>
        </form>
    </div>

    <aside class="space-y-6 text-sm lg:pt-20">
        <div>
            <p class="font-semibold text-automotive-900">E-mail</p>
            <a href="mailto:{{ config('legal.contact.general') }}" class="mt-1 block text-wrench-700 hover:text-wrench-800">{{ config('legal.contact.general') }}</a>
        </div>
        <div>
            <p class="font-semibold text-automotive-900">Privacidade e LGPD</p>
            <p class="mt-1 text-automotive-600">Pedidos de acesso, correção ou exclusão de dados pessoais.</p>
            <a href="mailto:{{ config('legal.contact.privacy') }}" class="mt-1 block text-wrench-700 hover:text-wrench-800">{{ config('legal.contact.privacy') }}</a>
        </div>
    </aside>
</div>
@endsection
