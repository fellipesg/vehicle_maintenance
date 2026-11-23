<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Uma resposta de FAQ é uma lista de trechos. O texto visível é a concatenação
 * deles; o trecho com href vira link, com o próprio texto como âncora, para o
 * FAQPage do JSON-LD repetir a frase palavra por palavra.
 *
 * @phpstan-type Part array{text: string, href?: string}
 * @phpstan-type Item array{question: string, parts: list<Part>}
 */
final class Faq
{
    /**
     * @param  list<Part>  $parts
     */
    public static function plain(array $parts): string
    {
        return implode('', array_map(fn (array $part): string => $part['text'], $parts));
    }

    /**
     * HTML da resposta, sem espaço entre os trechos. O link é o x-ui.link inline,
     * sem external: o aviso de nova aba entraria no texto e sairia do schema.
     *
     * @param  list<Part>  $parts
     */
    public static function html(array $parts): HtmlString
    {
        $html = '';

        foreach ($parts as $part) {
            if (! empty($part['href'])) {
                $html .= Blade::render(
                    '<x-ui.link :href="$href" variant="inline">{{ $text }}</x-ui.link>',
                    ['href' => $part['href'], 'text' => $part['text']],
                );

                continue;
            }

            $html .= e($part['text']);
        }

        return new HtmlString($html);
    }

    /**
     * @param  list<Item>  $items
     * @return list<array{question: string, answer: string}>
     */
    public static function pairs(array $items): array
    {
        return array_map(fn (array $item): array => [
            'question' => $item['question'],
            'answer' => self::plain($item['parts']),
        ], $items);
    }
}
