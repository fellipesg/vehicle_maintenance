<?php

namespace App\Http\Requests\Web;

use App\Enums\Portal;
use App\Http\Requests\Concerns\ValidatesWorkshopProfile;
use App\Models\Identity;
use App\Rules\AvailableForUserType;
use App\Rules\MatchesIdentityPassword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro próprio da oficina (/para-oficinas/cadastro): a pessoa responsável, a oficina e o endereço.
 *
 * O e-mail já pode ter identidade (a pessoa tem conta de proprietário e agora cadastra a oficina
 * dela): o que bloqueia é já existir conta **de oficina** nesse e-mail, e a senha pedida passa a ser
 * a da identidade, não uma nova. Ver App\Rules\AvailableForUserType e
 * App\Rules\MatchesIdentityPassword.
 */
class StoreWorkshopSignupRequest extends FormRequest
{
    use ValidatesWorkshopProfile;

    private Identity|false|null $resolvedIdentity = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeWorkshopProfileInput();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new AvailableForUserType(Portal::Workshop->value)],
            'password' => $this->existingIdentity() === null
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['required', 'string', new MatchesIdentityPassword($this->existingIdentity())],
            ...$this->workshopProfileRules(),
        ];
    }

    /**
     * Identidade que já existe para o e-mail enviado, quando existe: é a ela que a conta de oficina
     * se liga, como conta irmã. Resolvida uma vez por requisição.
     */
    public function existingIdentity(): ?Identity
    {
        if ($this->resolvedIdentity === false) {
            $this->resolvedIdentity = Identity::query()
                ->where('email', (string) $this->input('email'))
                ->first();
        }

        return $this->resolvedIdentity;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            ...$this->workshopProfileMessages(),
        ];
    }
}
