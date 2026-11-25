<?php

namespace App\Http\Requests\Web\Signup;

use App\Enums\Portal;
use App\Models\Identity;
use App\Rules\AvailableForUserType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Primeira etapa do cadastro em etapas: o perfil e o e-mail.
 *
 * É aqui que mora a **busca do e-mail**. O que recusa o cadastro não é o e-mail existir, é já
 * existir conta **do perfil escolhido** nele (App\Rules\AvailableForUserType): o mesmo endereço pode
 * ter um perfil de cada. Achando identidade de outro perfil, a etapa seguinte pede a senha dela em
 * vez de uma nova.
 *
 * Lojista fica de fora de propósito: ele entra por Google ou Apple, não por e-mail e senha
 * (.ai/rules/web-api.md, "Lojista signs up through a social provider").
 */
class StoreSignupProfileRequest extends FormRequest
{
    /**
     * Perfis que se cadastram por aqui => o `user_type` de cada um.
     *
     * @var array<string, string>
     */
    public const PROFILES = [
        'usuario' => 'user',
        'oficina' => 'workshop',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_scalar($this->input('email'))) {
            $this->merge(['email' => trim((string) $this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile' => ['required', 'string', Rule::in(array_keys(self::PROFILES))],
            'email' => [
                'required',
                'email',
                'max:255',
                new AvailableForUserType(self::PROFILES[$this->profileSlug()] ?? 'user'),
            ],
        ];
    }

    public function profile(): Portal
    {
        return Portal::forUserType(self::PROFILES[$this->profileSlug()] ?? 'user');
    }

    /**
     * Identidade que já existe para o e-mail, quando existe: é a ela que o novo perfil se liga.
     */
    public function existingIdentity(): ?Identity
    {
        return Identity::query()->where('email', (string) $this->validated('email'))->first();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile.required' => 'Escolha o tipo de conta que você quer criar.',
            'profile.in' => 'Escolha o tipo de conta que você quer criar.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido, como nome@exemplo.com.',
        ];
    }

    private function profileSlug(): string
    {
        return is_scalar($this->input('profile')) ? (string) $this->input('profile') : '';
    }
}
