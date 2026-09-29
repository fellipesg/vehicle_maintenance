<?php

namespace App\Support;

/**
 * Monta o <title> no padrão "{Página} · RevisaLog" (site público)
 * e "{Página} · {Área} · RevisaLog" (portais e admin).
 *
 * Recebe o conteúdo já renderizado da seção 'title', que o Blade entrega escapado;
 * o resultado deve ser impresso sem novo escape, do mesmo jeito que o @yield faz.
 */
class DocumentTitle
{
    public const BRAND = 'RevisaLog';

    private const SEPARATOR = ' · ';

    /**
     * Sufixos que algumas views ainda acrescentam ao título e que o layout passa a compor sozinho.
     *
     * @var list<string>
     */
    private const LEGACY_SUFFIXES = ['RevisaLog', 'Admin', 'Histórico de Manutenções'];

    public static function pageName(string $sectionTitle): string
    {
        $pageName = trim($sectionTitle);
        $suffixPattern = '/\s*[—–·|-]\s*(?:'.implode('|', array_map(
            fn (string $suffix): string => preg_quote($suffix, '/'),
            self::LEGACY_SUFFIXES,
        )).')$/iu';

        do {
            $previousPageName = $pageName;
            $pageName = trim(preg_replace($suffixPattern, '', $pageName) ?? $pageName);
        } while ($pageName !== $previousPageName);

        return $pageName;
    }

    public static function compose(string $sectionTitle, ?string $area = null): string
    {
        $pageName = self::pageName($sectionTitle);
        $parts = [];

        if ($pageName !== '' && mb_strtolower($pageName) !== mb_strtolower(self::BRAND)) {
            $parts[] = $pageName;
        }

        if (filled($area) && ! self::endsWithWord($pageName, $area)) {
            $parts[] = $area;
        }

        $parts[] = self::BRAND;

        return implode(self::SEPARATOR, $parts);
    }

    private static function endsWithWord(string $text, string $word): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'$/iu', $text) === 1;
    }
}
