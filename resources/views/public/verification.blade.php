@extends('layouts.app')

@section('title', 'Registro verificado — Revisalog')

@section('content')
<div class="mx-auto max-w-lg px-4 py-10">
    <p class="text-center text-sm font-semibold uppercase tracking-wide text-automotive-500">Revisalog</p>
    <h1 class="mt-2 text-center text-2xl font-bold text-automotive-900">Registro verificado</h1>

    <div class="card mt-8 space-y-4 text-center">
        @if($maintenance->verifiedWorkshop?->logo_url)
            <img src="{{ $maintenance->verifiedWorkshop->logo_url }}" alt="" class="mx-auto h-16 w-16 rounded-full object-cover">
        @endif
        <p class="text-lg font-semibold">{{ $maintenance->verifiedWorkshop?->name }}</p>
        <p class="text-sm text-automotive-600">{{ $maintenance->maintenance_type }} · {{ $maintenance->maintenance_date?->format('d/m/Y') }}</p>
        @if($maintenance->kilometers)
            <p class="text-sm text-automotive-500">{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</p>
        @endif
        <p class="font-mono text-sm text-automotive-700">Chassi: {{ $maskedChassis }}</p>
        <p class="text-xs text-automotive-500">
            Este registro foi criado pela oficina {{ $maintenance->verifiedWorkshop?->name }} em
            {{ $maintenance->verified_at?->format('d/m/Y H:i') }} na plataforma Revisalog e não pode ser alterado pelo proprietário.
        </p>
        <p class="font-mono text-sm">{{ $maintenance->verification_code }}</p>
        @if($qrSvg)
            <div class="mx-auto inline-block">{!! $qrSvg !!}</div>
        @endif
    </div>
</div>
@endsection
