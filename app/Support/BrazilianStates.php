<?php

namespace App\Support;

/**
 * As 27 unidades da federação, na ordem alfabética do nome do estado. Fonte única da lista de UF
 * das telas de cadastro (Minha oficina e Minha loja) e da regra de validação que as acompanha.
 */
final class BrazilianStates
{
    /**
     * @var array<string, string>
     */
    public const NAMES = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
        'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
        'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    /**
     * Siglas, para Rule::in.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::NAMES);
    }

    /**
     * "SP" => "São Paulo (SP)", pronto para <x-ui.select :options>.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::NAMES as $code => $name) {
            $options[$code] = "{$name} ({$code})";
        }

        return $options;
    }
}
