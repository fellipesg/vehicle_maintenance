<?php

namespace App\Support;

use App\Models\BlogCategory;

/**
 * Cena ilustrada e paleta da capa gerada de um post sem foto.
 *
 * A cena vem do próprio post (`cover_art`); quando ele não escolhe nenhuma,
 * cai na cena padrão da categoria. Categoria desconhecida recebe uma paleta
 * determinística pelo slug, para que a grade continue variada sem ajuste manual.
 */
class BlogCoverArt
{
    public const DEFAULT_SCENE = 'carro';

    /**
     * Cenas disponíveis, na ordem em que aparecem no formulário do admin.
     *
     * @var array<string, string>
     */
    public const SCENES = [
        'historico' => 'Relatório ao lado do veículo',
        'selo' => 'Selo de verificação da oficina',
        'revisao' => 'Calendário de revisão',
        'troca-de-oleo' => 'Motor aberto e funil de óleo',
        'documento' => 'Documento do veículo',
        'venda' => 'Venda do veículo',
        'carro' => 'Veículo (genérico)',
    ];

    /**
     * @var array<string, array{scene: string, from: string, to: string, accent: string}>
     */
    private const BY_CATEGORY = [
        'plataforma' => [
            'scene' => 'historico',
            'from' => '#162636',
            'to' => '#0b1c2c',
            'accent' => '#2ec4b6',
        ],
        'manutencao' => [
            'scene' => 'revisao',
            'from' => '#243041',
            'to' => '#0b1c2c',
            'accent' => '#4dd4c8',
        ],
        'documentacao' => [
            'scene' => 'documento',
            'from' => '#344453',
            'to' => '#162636',
            'accent' => '#99ebe1',
        ],
        'compra-e-venda' => [
            'scene' => 'venda',
            'from' => '#162636',
            'to' => '#124c47',
            'accent' => '#26a99d',
        ],
    ];

    /**
     * @return list<array{from: string, to: string, accent: string}>
     */
    private static function fallbackPalettes(): array
    {
        return array_values(array_map(
            static fn (array $art): array => [
                'from' => $art['from'],
                'to' => $art['to'],
                'accent' => $art['accent'],
            ],
            self::BY_CATEGORY,
        ));
    }

    public static function isScene(?string $scene): bool
    {
        return $scene !== null && array_key_exists($scene, self::SCENES);
    }

    /**
     * @return array{scene: string, from: string, to: string, accent: string, label: string}
     */
    public static function for(?BlogCategory $category, ?string $scene = null): array
    {
        $slug = $category?->slug ?? '';

        $art = self::BY_CATEGORY[$slug] ?? null;

        if ($art === null) {
            $palettes = self::fallbackPalettes();
            $art = $palettes[crc32($slug) % count($palettes)] + ['scene' => self::DEFAULT_SCENE];
        }

        if (self::isScene($scene)) {
            $art['scene'] = $scene;
        }

        return $art + ['label' => $category?->name ?? 'RevisaLog'];
    }
}
