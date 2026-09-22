@extends('layouts.app')

@section('title', $title)

@php
    // O texto vive em config/legal.php (mesma fonte do aceite no cadastro e da API do app).
    // Linhas "N. Título" viram seções; linhas "- item" viram lista.
    $blocks = [];
    foreach (array_slice(preg_split('/\R/', trim($content)), 1) as $line) {
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
<article class="mx-auto max-w-3xl px-4 py-12">
    <header class="border-b border-automotive-200 pb-6">
        <h1 class="text-3xl font-bold text-automotive-900">{{ $title }}</h1>
        <p class="mt-2 text-sm text-automotive-500">
            Versão em vigor desde {{ \Illuminate\Support\Carbon::parse($version)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') }}
        </p>
    </header>

    <div class="mt-8 space-y-4 text-[0.95rem] leading-relaxed text-automotive-700">
        @foreach($blocks as $block)
            @if($block['type'] === 'heading')
                <h2 class="pt-4 text-lg font-semibold text-automotive-900">{{ $block['text'] }}</h2>
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

    <p class="mt-12 rounded-lg border border-automotive-200 bg-white px-4 py-3 text-sm text-automotive-600">
        Ficou alguma dúvida? <a href="{{ route('contact.show') }}" class="font-medium text-wrench-700 underline hover:text-wrench-800">Fale conosco</a>.
    </p>
</article>
@endsection
