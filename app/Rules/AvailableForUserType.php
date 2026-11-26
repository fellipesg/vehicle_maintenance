<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O e-mail ainda não tem conta **deste papel**.
 *
 * Substitui o `unique:users,email` dos cadastros: com identidade compartilhada o endereço pode ter
 * uma conta por papel (`unique(identity_id, user_type)`), então o que impede o cadastro não é o
 * e-mail existir, é o papel já existir. A mensagem é a mesma de antes
 * (`lang/pt_BR/validation.php`, `custom.email.unique`), porque a saída oferecida continua a mesma:
 * entrar ou redefinir a senha.
 *
 * Quem acha o e-mail com outro papel é o App\Rules\MatchesIdentityPassword, que pede a senha da
 * identidade em vez de uma nova.
 */
class AvailableForUserType implements ValidationRule
{
    public function __construct(private string $userType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $taken = User::query()
            ->where('email', $value)
            ->where('user_type', $this->userType)
            ->exists();

        if ($taken) {
            $fail(trans('validation.custom.email.unique'));
        }
    }
}
