<?php

namespace App\Rules;

use App\Support\WorkshopMessageTemplateRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Recusa `{{variavel}}` que o renderer não troca.
 *
 * `WorkshopMessageTemplateRenderer::render` usa `strtr` com sete chaves exatas: o que não casa fica
 * no texto e chega ao cliente como está — `{{cliente}}`, `{{list_servicos}}`, e também
 * `{{ customer_name }}`, porque o espaço dentro das chaves faz a chave não casar. Nada avisava a
 * oficina, nem o formulário nem a validação.
 *
 * O recorte é o mesmo do teto de ativos e do plano de gatilhos
 * (`App\Http\Requests\Concerns\EnforcesWorkshopMessageTemplateLimit`): vale na **transição**, não no
 * estado. Texto reenviado igual ao que está gravado passa, senão um modelo que já tem uma variável
 * inventada trancaria a tela onde a oficina o corrige ou o desativa — e no update da web o corpo e o
 * título vão no mesmo formulário, então desativar reenvia os dois.
 */
class KnownMessageTemplateTags implements ValidationRule
{
    /**
     * @param  string|null  $stored  O valor já gravado neste campo, quando há modelo (update).
     */
    public function __construct(private readonly ?string $stored = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Estado, não transição: o texto chegou igual ao que já estava gravado.
        if ($this->stored !== null && $value === $this->stored) {
            return;
        }

        $unknown = self::unknownTagsIn($value);

        if ($unknown === []) {
            return;
        }

        $fail(sprintf(
            'O texto usa %s, %s. Use uma destas, escrita exatamente assim: %s.',
            implode(', ', array_map(fn (string $tag): string => '"'.$tag.'"', $unknown)),
            count($unknown) === 1
                ? 'que não é uma variável e chega ao cliente como está'
                : 'que não são variáveis e chegam ao cliente como estão',
            implode(', ', array_map(
                fn (string $name): string => '{{'.$name.'}}',
                array_keys(WorkshopMessageTemplateRenderer::TAGS),
            )),
        ));
    }

    /**
     * @return list<string>
     */
    public static function unknownTagsIn(string $text): array
    {
        if (preg_match_all('/\{\{[^{}]*\}\}/', $text, $matches) === 0) {
            return [];
        }

        $known = array_map(
            fn (string $name): string => '{{'.$name.'}}',
            array_keys(WorkshopMessageTemplateRenderer::TAGS),
        );

        return array_values(array_unique(array_filter(
            $matches[0],
            fn (string $tag): bool => ! in_array($tag, $known, true),
        )));
    }
}
