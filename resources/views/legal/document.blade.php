@extends('layouts.app')

@section('title', $title.' — Revisalog')

@php
    // Texto em config/legal.php (mesma fonte do aceite no cadastro e da API do app).
    // Linhas "N. Título" viram seções e "- item" vira lista; a 1ª linha repete o título.
    $blocks = [];
    foreach (array_slice(preg_split('/\R/', trim((string) $content)), 1) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^\d+\.\s+/', $line)) {
            $blocks[] = ['type' => 'heading', 'text' => $line];
        } elseif (str_starts_with($line, '- ')) {
            $last = array_key_last($blocks);
            if ($last !== null && $blocks[$last]['type'] === 'list') {
                $blocks[$last]['items'][] = substr($line, 2);
            } else {
                $blocks[] = ['type' => 'list', 'items' => [substr($line, 2)]];
            }
        } else {
            $blocks[] = ['type' => 'paragraph', 'text' => $line];
        }
    }

    $linkEmails = fn (string $text) => preg_replace(
        '/[\w.+-]+@[\w-]+(\.[\w-]+)+/',
        '<a href="mailto:$0" class="font-medium text-wrench-700 underline hover:text-wrench-800">$0</a>',
        e($text),
    );
@endphp

@section('content')
<article class="mx-auto max-w-3xl px-4 py-10">
    <p class="text-sm font-semibold uppercase tracking-wide text-automotive-500">Revisalog</p>
    <h1 class="mt-2 text-2xl font-bold text-automotive-900">{{ $title }}</h1>
    @if(! empty($version))
        <p class="mt-2 text-xs text-automotive-400">
            Versão {{ $version }} · em vigor desde {{ \Illuminate\Support\Carbon::parse($version)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') }}
        </p>
    @endif

    <div class="card mt-8 space-y-4 text-sm leading-relaxed text-automotive-700">
        @foreach($blocks as $block)
            @if($block['type'] === 'heading')
                <h2 class="pt-2 text-base font-semibold text-automotive-900">{{ $block['text'] }}</h2>
            @elseif($block['type'] === 'list')
                <ul class="list-disc space-y-1.5 pl-5">
                    @foreach($block['items'] as $item)
                        <li>{!! $linkEmails($item) !!}</li>
                    @endforeach
                </ul>
            @else
                <p>{!! $linkEmails($block['text']) !!}</p>
            @endif
        @endforeach
    </div>

    <p class="mt-8 text-sm text-automotive-600">
        Ficou alguma dúvida? <a href="{{ route('contact.show') }}" class="font-medium text-wrench-700 underline hover:text-wrench-800">Fale conosco</a>.
    </p>
</article>
@endsection
