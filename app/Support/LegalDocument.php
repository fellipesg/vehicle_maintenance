<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Texto legal (config/legal.php, a mesma fonte do aceite no cadastro e da API do app) em blocos
 * para as páginas /termos e /privacidade.
 *
 * Formato do texto: a 1ª linha repete o título da página e é descartada; "N. Título" abre uma
 * seção; "- item" vira item de lista (itens seguidos formam uma lista só); as demais linhas são
 * parágrafos. Cada seção ganha um id estável tirado do título sem o número ("7. Seus direitos"
 * vira "seus-direitos"), usado pelo sumário e por links diretos como /privacidade#seus-direitos:
 * renumerar as seções não quebra os links que o suporte já enviou.
 */
final readonly class LegalDocument
{
    private const SECTION_PATTERN = '/^(\d+)\.\s+(.+)$/u';

    /**
     * @param  list<array{type: 'heading', text: string, id: string}|array{type: 'paragraph', text: string}|array{type: 'list', items: list<string>}>  $blocks
     */
    public function __construct(public array $blocks) {}

    public static function fromText(?string $content): self
    {
        $lines = array_slice(preg_split('/\R/u', trim((string) $content)) ?: [], 1);
        $blocks = [];
        $usedIds = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match(self::SECTION_PATTERN, $line, $matches) === 1) {
                $blocks[] = [
                    'type' => 'heading',
                    'text' => $line,
                    'id' => self::uniqueId($matches[2], (int) $matches[1], $usedIds),
                ];

                continue;
            }

            if (str_starts_with($line, '- ')) {
                $item = trim(substr($line, 2));
                $lastIndex = array_key_last($blocks);

                if ($lastIndex !== null && $blocks[$lastIndex]['type'] === 'list') {
                    $blocks[$lastIndex]['items'][] = $item;
                } else {
                    $blocks[] = ['type' => 'list', 'items' => [$item]];
                }

                continue;
            }

            $blocks[] = ['type' => 'paragraph', 'text' => $line];
        }

        return new self($blocks);
    }

    /**
     * Parágrafo de abertura (antes da 1ª seção), usado como lead e como meta description.
     */
    public function introduction(): ?string
    {
        $firstBlock = $this->blocks[0] ?? null;

        return $firstBlock !== null && $firstBlock['type'] === 'paragraph' ? $firstBlock['text'] : null;
    }

    /**
     * Seções na ordem do texto, para o sumário "Nesta página".
     *
     * @return list<array{id: string, text: string}>
     */
    public function sections(): array
    {
        $sections = [];

        foreach ($this->blocks as $block) {
            if ($block['type'] === 'heading') {
                $sections[] = ['id' => $block['id'], 'text' => $block['text']];
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, true>  $usedIds
     */
    private static function uniqueId(string $title, int $number, array &$usedIds): string
    {
        $baseId = Str::slug($title) ?: 'secao-'.$number;
        $id = $baseId;
        $suffix = 2;

        while (isset($usedIds[$id])) {
            $id = $baseId.'-'.$suffix++;
        }

        $usedIds[$id] = true;

        return $id;
    }
}
