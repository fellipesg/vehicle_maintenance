<?php

namespace App\Support;

use BackedEnum;
use Illuminate\View\ComponentSlot;
use InvalidArgumentException;
use Stringable;

/**
 * Validação das props dos componentes x-ui.* (resources/views/components/ui).
 *
 * Valor fora da lista ou prop obrigatória ausente é erro de quem escreveu a view. Em local e testing
 * a exceção sobe, para o erro aparecer já no desenvolvimento (mesma regra de IconLibrary). Nos demais
 * ambientes ela é só reportada e o componente segue com o valor padrão, para um status novo vindo do
 * banco não derrubar a página.
 *
 * Ex.: $variant = UiProps::oneOf('x-ui.badge', 'variant', $variant, ['neutral', 'success'], 'neutral');
 */
final class UiProps
{
    /**
     * Devolve o valor quando ele está em $allowed; senão falha (ver fail()) e devolve $fallback.
     *
     * @param  list<string>  $allowed
     */
    public static function oneOf(string $component, string $prop, mixed $value, array $allowed, string $fallback): string
    {
        $normalized = self::normalize($value);

        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        self::fail(sprintf(
            '%s: %s "%s" não existe. Use %s.',
            $component,
            $prop,
            $normalized,
            implode(', ', $allowed),
        ));

        return $fallback;
    }

    /**
     * Falha (ver fail()) quando a prop obrigatória está vazia. Slot só com espaços ou comentários
     * conta como vazio.
     */
    public static function required(string $component, string $prop, mixed $value, string $hint = ''): void
    {
        if (! self::isBlank($value)) {
            return;
        }

        self::fail(trim(sprintf('%s precisa de %s. %s', $component, $prop, $hint)));
    }

    public static function isBlank(mixed $value): bool
    {
        if ($value instanceof ComponentSlot) {
            return ! $value->hasActualContent();
        }

        return trim(self::normalize($value)) === '';
    }

    /**
     * Em local e testing lança a exceção; nos demais ambientes só a reporta.
     *
     * @throws InvalidArgumentException em local e testing
     */
    public static function fail(string $message): void
    {
        $exception = new InvalidArgumentException($message);

        if (app()->environment(['local', 'testing'])) {
            throw $exception;
        }

        report($exception);
    }

    private static function normalize(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            is_string($value) => $value,
            is_int($value), is_float($value), $value instanceof Stringable => (string) $value,
            default => '',
        };
    }
}
