<?php

namespace App\Support\Seo;

use Illuminate\Support\Str;

/**
 * O post de resposta direta abre com uma citação Markdown (`> ...`). A view
 * tira essa linha do corpo e mostra no lead. O bloco "## Perguntas frequentes"
 * vira o FAQPage, com o mesmo texto que o Markdown renderiza.
 */
final class BlogArticle
{
    /**
     * @return array{lead: ?string, body: string}
     */
    public static function split(string $markdown): array
    {
        if (preg_match('/\A> ([^\n]+)\R\R([\s\S]*)\z/u', $markdown, $match) !== 1) {
            return ['lead' => null, 'body' => $markdown];
        }

        return [
            'lead' => trim($match[1]),
            'body' => $match[2],
        ];
    }

    /**
     * @return list<array{question: string, parts: list<array{text: string}>}>
     */
    public static function faqs(string $markdown): array
    {
        if (preg_match('/^## Perguntas frequentes\s*\R([\s\S]*)\z/mu', $markdown, $section) !== 1) {
            return [];
        }

        preg_match_all('/^### (.+)\R+([\s\S]*?)(?=^### |\z)/mu', $section[1], $questions, PREG_SET_ORDER);

        $items = [];

        foreach ($questions as $question) {
            $text = self::plainMarkdown($question[2]);

            if ($text === '') {
                continue;
            }

            $items[] = [
                'question' => trim($question[1]),
                'parts' => [['text' => $text]],
            ];
        }

        return $items;
    }

    public static function plainMarkdown(string $markdown): string
    {
        $html = Str::markdown(trim($markdown));

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
    }

    public static function wordCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, fn (string $word): bool => $word !== ''));
    }
}
