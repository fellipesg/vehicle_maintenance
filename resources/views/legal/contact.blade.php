@extends('layouts.app')

@section('title', 'Contato — Revisalog')

@if($turnstileSiteKey)
    @push('head')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif

@section('content')
<div class="mx-auto max-w-xl px-4 py-10">
    <p class="text-sm font-semibold uppercase tracking-wide text-automotive-500">Revisalog</p>
    <h1 class="mt-2 text-2xl font-bold text-automotive-900">Contato</h1>
    <p class="mt-3 text-sm leading-relaxed text-automotive-600">
        Fale com a gente em
        <a href="mailto:{{ $supportEmail }}" class="font-medium text-wrench-700 hover:underline">{{ $supportEmail }}</a>
        ou envie a mensagem abaixo.
    </p>

    <form method="POST" action="{{ route('contact.store') }}" class="card mt-8 space-y-4">
        @csrf

        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="name" class="form-label">Nome *</label>
            <input type="text" name="name" id="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="120"
                   class="form-input" autocomplete="name">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="form-label">E-mail *</label>
            <input type="email" name="email" id="email" value="{{ old('email', auth()->user()?->email) }}" required maxlength="255"
                   class="form-input" autocomplete="email">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="subject" class="form-label">Assunto *</label>
            <select name="subject" id="subject" required class="form-select">
                <option value="" disabled @selected(! old('subject', request('assunto')))>Selecione</option>
                @foreach($subjects as $value => $label)
                    <option value="{{ $value }}" @selected(old('subject', request('assunto')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="message" class="form-label">Mensagem *</label>
            <textarea name="message" id="message" required maxlength="4000" rows="6"
                      class="form-input">{{ old('message') }}</textarea>
            @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        @if($turnstileSiteKey)
            <div>
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-language="pt-br"></div>
                @error('cf-turnstile-response')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif

        <p class="text-xs text-automotive-500">
            Usamos seus dados só para responder esta mensagem. Veja a
            <a href="{{ route('legal.privacy') }}" class="underline hover:text-wrench-700">Política de privacidade</a>.
        </p>

        <button type="submit" class="btn-primary">Enviar</button>
    </form>
</div>
@endsection
