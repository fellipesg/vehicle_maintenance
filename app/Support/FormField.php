<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/**
 * Regras comuns dos componentes de formulário (resources/views/components/ui): id determinístico a
 * partir do name, chave de erro e de old input, estado marcado e a ligação do controle com o rótulo,
 * a dica e o erro do <x-ui.field>.
 *
 * O id sai do name para o campo e o controle chegarem ao mesmo valor sem conversar: o controle é
 * renderizado dentro do slot do campo antes do próprio campo, então o campo só recebe o HTML pronto.
 * 'items[0][name]' e 'items.0.name' viram items_0_name; a dica fica em {id}-hint e o erro em
 * {id}-error.
 */
class FormField
{
    /**
     * Tipos de <input> que não recebem rótulo nem descrição do campo.
     *
     * @var list<string>
     */
    private const UNLABELABLE_INPUT_TYPES = ['hidden', 'submit', 'button', 'reset', 'image'];

    /**
     * Nome amigável dos tipos mais comuns do atributo accept, para a regra do <x-ui.file-input>.
     *
     * @var array<string, string>
     */
    private const ACCEPT_LABELS = [
        'application/pdf' => 'PDF',
        '.pdf' => 'PDF',
        'image/jpeg' => 'JPG',
        'image/jpg' => 'JPG',
        '.jpg' => 'JPG',
        '.jpeg' => 'JPG',
        'image/png' => 'PNG',
        '.png' => 'PNG',
        'image/webp' => 'WEBP',
        '.webp' => 'WEBP',
        'image/heic' => 'HEIC',
        '.heic' => 'HEIC',
        'image/*' => 'imagens',
        'video/*' => 'vídeos',
        'audio/*' => 'áudios',
        'text/xml' => 'XML',
        'application/xml' => 'XML',
        '.xml' => 'XML',
        'text/csv' => 'CSV',
        '.csv' => 'CSV',
    ];

    /**
     * Id do controle a partir do name: colchetes e pontos viram "_" e o "[]" final de campo múltiplo
     * sai. Com sufixo (o valor do radio ou do checkbox de lista), ele entra depois de "_".
     */
    public static function controlId(?string $name, ?string $suffix = null): ?string
    {
        if (blank($name)) {
            return null;
        }

        $base = (string) preg_replace('/\[\]$/', '', trim($name));
        $base = str_replace(['[', ']', '.'], ['_', '', '_'], $base);
        $base = trim((string) preg_replace(['/[^A-Za-z0-9_-]+/', '/_+/'], '_', $base), '_');

        if ($base === '') {
            $base = 'campo';
        }

        if ($suffix === null) {
            return $base;
        }

        $suffixSlug = Str::slug($suffix, '_');

        return $base.'_'.($suffixSlug !== '' ? $suffixSlug : substr(md5($suffix), 0, 8));
    }

    /**
     * Chave do name no MessageBag e no old input: 'items[0][name]' vira items.0.name e 'photos[]'
     * vira photos.
     */
    public static function errorKey(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        $key = (string) preg_replace('/\[\]$/', '', trim($name));
        $key = str_replace(['[', ']'], ['.', ''], $key);

        return trim((string) preg_replace('/\.+/', '.', $key), '.');
    }

    /**
     * O name termina em "[]" (checkbox de lista, select múltiplo, vários arquivos).
     */
    public static function isArrayName(?string $name): bool
    {
        return filled($name) && str_ends_with(trim($name), '[]');
    }

    /**
     * Primeira mensagem de erro do campo no bag pedido. Procura também em "{chave}.*", onde caem os
     * erros de cada item de um campo de lista (photos.0, photos.1).
     */
    public static function error(mixed $errors, ?string $name, ?string $bag = null): ?string
    {
        $key = self::errorKey($name);

        if (! $errors instanceof ViewErrorBag || blank($key)) {
            return null;
        }

        $messages = $errors->getBag(filled($bag) ? $bag : 'default');
        $message = $messages->first($key) ?: $messages->first($key.'.*');

        return filled($message) ? (string) $message : null;
    }

    /**
     * Valor antigo do campo (old input) ou o padrão. Sem sessão na requisição, o padrão.
     */
    public static function old(?string $name, mixed $default = null): mixed
    {
        $key = self::errorKey($name);

        if (blank($key)) {
            return $default;
        }

        return old($key, $default);
    }

    /**
     * A requisição voltou de um envio com erro e trouxe old input. É o que separa "checkbox
     * desmarcado pelo usuário" (ausente do old input) de "primeira visita" (vale o padrão).
     */
    public static function hasOldInput(): bool
    {
        $request = request();

        return $request->hasSession() && $request->session()->hasOldInput();
    }

    /**
     * Estado marcado de checkbox, radio e switch: depois de um envio com erro vale o que o usuário
     * enviou; na primeira visita, o padrão.
     */
    public static function isChecked(?string $name, string $value, bool $default): bool
    {
        if (blank($name) || ! self::hasOldInput()) {
            return $default;
        }

        $old = old((string) self::errorKey($name));

        if (is_array($old)) {
            return in_array($value, array_map('strval', $old), true);
        }

        return $old !== null && (string) $old === $value;
    }

    /**
     * O valor está entre os selecionados (valor único, lista ou enum), comparando como texto.
     */
    public static function isSelected(mixed $selected, string $value): bool
    {
        if ($selected instanceof BackedEnum) {
            $selected = $selected->value;
        }

        if (is_iterable($selected)) {
            foreach ($selected as $selectedValue) {
                if (self::isSelected($selectedValue, $value)) {
                    return true;
                }
            }

            return false;
        }

        if ($selected === null || $selected === false) {
            return false;
        }

        return (string) $selected === $value;
    }

    /**
     * Junta ids de aria-describedby sem vazios nem repetidos. Null quando não sobra nenhum.
     */
    public static function describedBy(?string ...$ids): ?string
    {
        $tokens = [];

        foreach ($ids as $id) {
            foreach (preg_split('/\s+/', trim((string) $id)) ?: [] as $token) {
                if ($token !== '' && ! in_array($token, $tokens, true)) {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens === [] ? null : implode(' ', $tokens);
    }

    /**
     * Normaliza as opções do <x-ui.select>. Aceita:
     * - mapa valor => rótulo: ['sp' => 'São Paulo'] (também Collection, como pluck('name', 'id'));
     * - lista de itens: [['value' => 'sp', 'label' => 'São Paulo', 'disabled' => false]];
     * - grupos: ['Sudeste' => ['sp' => 'São Paulo']], que viram <optgroup>;
     * - casos de enum com valor (BackedEnum), com o rótulo de label() quando o enum tiver.
     *
     * @param  iterable<array-key, mixed>|null  $options
     * @return list<array{type: 'option', value: string, label: string, disabled: bool}|array{type: 'group', label: string, options: list<array{type: 'option', value: string, label: string, disabled: bool}>}>
     */
    public static function options(?iterable $options): array
    {
        $normalized = [];

        foreach ($options ?? [] as $key => $option) {
            if ($option instanceof BackedEnum) {
                $normalized[] = self::option($option->value, method_exists($option, 'label') ? $option->label() : $option->name);

                continue;
            }

            if (is_array($option) && array_key_exists('label', $option)) {
                $normalized[] = self::option($option['value'] ?? $key, $option['label'], (bool) ($option['disabled'] ?? false));

                continue;
            }

            if (is_iterable($option)) {
                $normalized[] = [
                    'type' => 'group',
                    'label' => (string) $key,
                    'options' => array_values(array_filter(
                        self::options($option),
                        fn (array $item): bool => $item['type'] === 'option',
                    )),
                ];

                continue;
            }

            $normalized[] = self::option($key, $option);
        }

        return $normalized;
    }

    /**
     * Liga o controle do slot do <x-ui.field> ao rótulo, à dica e ao erro. Acha o primeiro controle
     * rotulável (input que não seja hidden/submit/button/reset/image, select ou textarea), dá a ele
     * o id de reserva quando não tem um, junta em aria-describedby os ids que $describedByFor
     * devolve e, com erro, marca aria-invalid="true". Assim um controle legado escrito à mão (um
     * <input class="form-input">) fica ligado sem repetir nada; num <x-ui.input> nada se duplica.
     *
     * Devolve o id final do controle (o que ele já tinha ou o de reserva): é dele que saem {id}-hint
     * e {id}-error. Sem controle rotulável no slot, devolve o HTML intacto e o id de reserva.
     *
     * @param  callable(string): list<string|null>  $describedByFor  recebe o id final do controle
     * @return array{html: string, id: string, found: bool}
     */
    public static function wireControl(string $html, string $fallbackId, callable $describedByFor, bool $invalid): array
    {
        $pattern = '/<(input|select|textarea)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/i';

        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            [$tag, $offset] = $match[0];
            $tagName = strtolower($match[1][0]);
            $attributeSource = $match[2][0];
            $selfClosing = preg_match('/\/\s*$/', $attributeSource) === 1;
            $attributes = self::parseAttributes((string) preg_replace('/\/\s*$/', '', $attributeSource));

            if ($tagName === 'input' && in_array(strtolower((string) self::attributeValue($attributes, 'type')), self::UNLABELABLE_INPUT_TYPES, true)) {
                continue;
            }

            $controlId = self::attributeValue($attributes, 'id');

            if (blank($controlId)) {
                $controlId = $fallbackId;
                $attributes = self::setAttribute($attributes, 'id', $controlId);
            }

            $describedBy = self::describedBy(self::attributeValue($attributes, 'aria-describedby'), ...$describedByFor($controlId));

            if ($describedBy !== null) {
                $attributes = self::setAttribute($attributes, 'aria-describedby', $describedBy);
            }

            if ($invalid && self::attributeValue($attributes, 'aria-invalid') === null) {
                $attributes = self::setAttribute($attributes, 'aria-invalid', 'true');
            }

            $rebuilt = '<'.$match[1][0].self::renderAttributes($attributes).($selfClosing ? ' />' : '>');

            return [
                'html' => substr_replace($html, $rebuilt, $offset, strlen($tag)),
                'id' => $controlId,
                'found' => true,
            ];
        }

        return ['html' => $html, 'id' => $fallbackId, 'found' => false];
    }

    /**
     * Texto da regra do <x-ui.file-input>: "PDF, JPG ou PNG · até 10 MB · até 4 arquivos".
     */
    public static function fileRules(?string $accept, int|float|null $maxMb = null, ?int $maxFiles = null): ?string
    {
        $parts = array_values(array_filter([
            self::acceptLabel($accept),
            $maxMb !== null && $maxMb > 0 ? 'até '.self::megabytes($maxMb) : null,
            $maxFiles !== null && $maxFiles > 1 ? "até {$maxFiles} arquivos" : null,
        ]));

        if ($parts === []) {
            return null;
        }

        return Str::ucfirst(implode(' · ', $parts));
    }

    /**
     * Tipos do atributo accept em texto: "image/jpeg,image/png,.pdf" vira "JPG, PNG ou PDF".
     */
    public static function acceptLabel(?string $accept): ?string
    {
        if (blank($accept)) {
            return null;
        }

        $labels = [];

        foreach (explode(',', $accept) as $type) {
            $type = strtolower(trim($type));

            if ($type === '') {
                continue;
            }

            $label = self::ACCEPT_LABELS[$type] ?? strtoupper(ltrim(Str::afterLast($type, '/'), '.'));

            if ($label !== '' && $label !== '*' && ! in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        if ($labels === []) {
            return null;
        }

        $last = array_pop($labels);

        return $labels === [] ? $last : implode(', ', $labels).' ou '.$last;
    }

    /**
     * Megabytes em pt-BR: 10 vira "10 MB" e 2.5 vira "2,5 MB".
     */
    public static function megabytes(int|float $megabytes): string
    {
        $decimals = floor($megabytes) == $megabytes ? 0 : 1;

        return number_format($megabytes, $decimals, ',', '.').' MB';
    }

    /**
     * @return array{type: 'option', value: string, label: string, disabled: bool}
     */
    private static function option(mixed $value, mixed $label, bool $disabled = false): array
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return [
            'type' => 'option',
            'value' => (string) $value,
            'label' => (string) $label,
            'disabled' => $disabled,
        ];
    }

    /**
     * Atributos de uma tag, na ordem, com o texto original de cada um para reescrever só o que mudou.
     * Valores entre aspas são consumidos inteiros, então um "id=" dentro de um placeholder não conta.
     *
     * @return list<array{name: string, value: string|null, raw: string}>
     */
    private static function parseAttributes(string $source): array
    {
        preg_match_all(
            '/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',
            $source,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        return array_map(fn (array $match): array => [
            'name' => (string) $match[1],
            'value' => $match[2] ?? $match[3] ?? $match[4] ?? null,
            'raw' => (string) $match[0],
        ], $matches);
    }

    /**
     * Valor decodificado do atributo, ou null quando ele não existe. Atributo sem valor (required)
     * devolve ''.
     *
     * @param  list<array{name: string, value: string|null, raw: string}>  $attributes
     */
    private static function attributeValue(array $attributes, string $name): ?string
    {
        foreach ($attributes as $attribute) {
            if (strcasecmp($attribute['name'], $name) === 0) {
                return $attribute['value'] === null ? '' : html_entity_decode($attribute['value'], ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, value: string|null, raw: string}>  $attributes
     * @return list<array{name: string, value: string|null, raw: string}>
     */
    private static function setAttribute(array $attributes, string $name, string $value): array
    {
        $attribute = ['name' => $name, 'value' => e($value), 'raw' => $name.'="'.e($value).'"'];

        foreach ($attributes as $index => $existing) {
            if (strcasecmp($existing['name'], $name) === 0) {
                $attributes[$index] = $attribute;

                return $attributes;
            }
        }

        $attributes[] = $attribute;

        return $attributes;
    }

    /**
     * @param  list<array{name: string, value: string|null, raw: string}>  $attributes
     */
    private static function renderAttributes(array $attributes): string
    {
        return implode('', array_map(fn (array $attribute): string => ' '.$attribute['raw'], $attributes));
    }
}
