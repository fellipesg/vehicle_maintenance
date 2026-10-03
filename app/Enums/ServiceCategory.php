<?php

namespace App\Enums;

/**
 * Categoria do serviço de uma manutenção (maintenances.service_category). Fonte única dos rótulos
 * em pt-BR que antes eram copiados em cada view ($categories = ['mechanical' => 'Mecânica', ...]).
 */
enum ServiceCategory: string
{
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';
    case Suspension = 'suspension';
    case Painting = 'painting';
    case Finishing = 'finishing';
    case Interior = 'interior';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mechanical => 'Mecânica',
            self::Electrical => 'Elétrica',
            self::Suspension => 'Suspensão',
            self::Painting => 'Pintura',
            self::Finishing => 'Acabamento',
            self::Interior => 'Interior',
            self::Other => 'Outros',
        };
    }

    /**
     * Rótulo de um valor gravado no banco, ou null quando está vazio ou é desconhecido.
     */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value)?->label();
    }

    /**
     * Valores aceitos na validação: Rule::in(ServiceCategory::values()).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }

    /**
     * Opções para <x-ui.select :options>, na ordem do formulário.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = $category->label();
        }

        return $options;
    }
}
