<?php

namespace App\Support;

use InvalidArgumentException;
use JsonException;

/**
 * Ícones Heroicons v2 (MIT, Tailwind Labs) do design system, guardados em resources/js/ui/icons.json.
 * O mesmo arquivo alimenta o componente <x-ui.icon> e o helper icon() de resources/js/ui/icons.js,
 * então Blade e templates JS desenham o mesmo traço. O JSON é lido uma vez por processo.
 *
 * Variantes: outline (24px, traço 1,5, o padrão) e solid (mini de 20px, preenchido, para 16px em
 * badge, alerta e tabela densa). Ícone novo: copie o conteúdo interno do SVG de
 * heroicons/optimized/24/outline e de optimized/20/solid para as duas chaves do JSON.
 */
class IconLibrary
{
    public const DEFAULT_VARIANT = 'outline';

    /**
     * @var list<string>
     */
    public const VARIANTS = ['outline', 'solid'];

    /**
     * @var array{_svg: array<string, array<string, string>>, outline: array<string, string>, solid: array<string, string>}|null
     */
    private static ?array $icons = null;

    public static function path(): string
    {
        return resource_path('js/ui/icons.json');
    }

    /**
     * Conteúdo interno do <svg> (os <path>) do ícone.
     *
     * @throws InvalidArgumentException quando a variante ou o nome não existem
     */
    public static function body(string $name, string $variant = self::DEFAULT_VARIANT): string
    {
        $icons = self::variantIcons($variant);

        if (! array_key_exists($name, $icons)) {
            throw new InvalidArgumentException(self::unknownIconMessage($name, $variant, array_keys($icons)));
        }

        return $icons[$name];
    }

    /**
     * Versão de body() para views: em local e testing o erro sobe, para o nome errado aparecer já no
     * desenvolvimento; nos demais ambientes ele só é reportado e o ícone não é desenhado, para um
     * ícone decorativo não derrubar a página.
     */
    public static function bodyForView(string $name, string $variant = self::DEFAULT_VARIANT): ?string
    {
        try {
            return self::body($name, $variant);
        } catch (InvalidArgumentException $exception) {
            if (app()->environment(['local', 'testing'])) {
                throw $exception;
            }

            report($exception);

            return null;
        }
    }

    public static function has(string $name, string $variant = self::DEFAULT_VARIANT): bool
    {
        return in_array($variant, self::VARIANTS, true)
            && array_key_exists($name, self::icons()[$variant]);
    }

    /**
     * Atributos do elemento <svg> da variante (viewBox, fill, stroke...).
     *
     * @return array<string, string>
     */
    public static function svgAttributes(string $variant = self::DEFAULT_VARIANT): array
    {
        self::variantIcons($variant);

        return self::icons()['_svg'][$variant];
    }

    /**
     * @return list<string>
     */
    public static function names(string $variant = self::DEFAULT_VARIANT): array
    {
        return array_keys(self::variantIcons($variant));
    }

    /**
     * Esquece o JSON lido, para a próxima chamada ler o arquivo de novo.
     */
    public static function flush(): void
    {
        self::$icons = null;
    }

    /**
     * @return array<string, string>
     */
    private static function variantIcons(string $variant): array
    {
        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Variante de ícone "%s" não existe. Use %s.',
                $variant,
                implode(' ou ', self::VARIANTS),
            ));
        }

        return self::icons()[$variant];
    }

    /**
     * @return array{_svg: array<string, array<string, string>>, outline: array<string, string>, solid: array<string, string>}
     */
    private static function icons(): array
    {
        if (self::$icons !== null) {
            return self::$icons;
        }

        try {
            $icons = json_decode((string) file_get_contents(self::path()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('resources/js/ui/icons.json não é um JSON válido: '.$exception->getMessage(), 0, $exception);
        }

        return self::$icons = $icons;
    }

    /**
     * @param  list<string>  $availableNames
     */
    private static function unknownIconMessage(string $name, string $variant, array $availableNames): string
    {
        $message = sprintf('Ícone "%s" não existe na variante %s de resources/js/ui/icons.json.', $name, $variant);

        $closestName = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($availableNames as $availableName) {
            $distance = levenshtein($name, $availableName);

            if ($distance < $closestDistance) {
                $closestName = $availableName;
                $closestDistance = $distance;
            }
        }

        if ($closestName !== null && $closestDistance <= 3) {
            $message .= sprintf(' Você quis dizer "%s"?', $closestName);
        }

        return $message.' Para um ícone novo, copie o SVG do Heroicons v2 (optimized/24/outline e optimized/20/solid) para as duas variantes do JSON.';
    }
}
