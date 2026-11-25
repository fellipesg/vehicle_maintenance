<?php

namespace App\Rules;

use App\Models\Identity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * A senha informada é a da identidade que já existe para este e-mail.
 *
 * É o caminho do segundo papel: quem já tem conta na RevisaLog e se cadastra para outro perfil não
 * escolhe uma senha nova — a senha é da identidade e vale para as contas irmãs. Pedir uma senha
 * nova aqui ou trocaria a senha da conta que já existe (quem controla a caixa de entrada não é
 * necessariamente quem está no formulário), ou deixaria a pessoa com duas senhas para o mesmo
 * e-mail.
 *
 * Não revela nada que o visitante já não soubesse: a mensagem só aparece depois de ele digitar um
 * e-mail que tem conta, e o cadastro já dizia isso em "Este e-mail já está cadastrado.".
 */
class MatchesIdentityPassword implements ValidationRule
{
    public const MESSAGE = 'Este e-mail já tem conta na RevisaLog. Informe a senha dessa conta para criar este perfil.';

    public function __construct(private Identity $identity) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Hash::check($value, (string) $this->identity->password)) {
            $fail(self::MESSAGE);
        }
    }
}
