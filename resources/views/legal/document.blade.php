@extends('layouts.app')

@section('title', $title.' — Revisalog')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10">
    <p class="text-sm font-semibold uppercase tracking-wide text-automotive-500">Revisalog</p>
    <h1 class="mt-2 text-2xl font-bold text-automotive-900">{{ $title }}</h1>
    @if(! empty($version))
        <p class="mt-2 text-xs text-automotive-400">Versão {{ $version }}</p>
    @endif
    <div class="card mt-8 whitespace-pre-line text-sm leading-relaxed text-automotive-700">{{ $content }}</div>
</div>
@endsection
