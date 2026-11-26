<?php

namespace App\Support\Account;

use App\Enums\Portal;
use App\Models\Identity;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Estado do cadastro em etapas (/cadastro): perfil e e-mail, senha, dados do perfil.
 *
 * Mora na sessão, e não em campos escondidos do formulário, por um motivo só: a senha. Um
 * `<input type="hidden">` com senha viaja no HTML, fica no histórico do navegador e volta em
 * qualquer reenvio do formulário.
 *
 * Mesmo na sessão, **a senha em claro nunca é guardada**: o que fica é o hash
 * (`rememberNewPassword`), que o cast `hashed` de App\Models\Identity aceita como já pronto. No
 * caminho da conta irmã nem isso é preciso — a senha é a da identidade, que já existe, e aqui só
 * fica a marca de que a pessoa a soube (`markIdentityVerified`).
 *
 * O fluxo expira em 30 minutos: a marca de "senha conferida" é derivada de credencial e não pode
 * ficar viva pelas duas horas da sessão.
 */
final class SignupFlow
{
    private const KEY = 'signup';

    private const TTL_MINUTES = 30;

    public function __construct(private Session $session) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->session());
    }

    /**
     * Primeira etapa concluída: o perfil escolhido e o e-mail conferido. `$identity` vem preenchida
     * quando o e-mail já tem conta de outro papel — é a conta irmã por nascer.
     */
    public function start(Portal $profile, string $email, ?Identity $identity): void
    {
        $this->session->put(self::KEY, [
            'profile' => $profile->value,
            'email' => $email,
            'identity_id' => $identity?->getKey(),
            'password_hash' => null,
            'identity_verified' => false,
            'started_at' => now()->getTimestamp(),
        ]);
    }

    public function profile(): ?Portal
    {
        $value = $this->state()['profile'] ?? null;

        return is_string($value) ? Portal::tryFrom($value) : null;
    }

    public function email(): ?string
    {
        $email = $this->state()['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * A identidade que este cadastro vai reaproveitar, relida do banco a cada etapa: guardar o id e
     * não o objeto evita decidir a etapa seguinte com uma identidade que foi apagada no meio.
     */
    public function identity(): ?Identity
    {
        $id = $this->state()['identity_id'] ?? null;

        return is_int($id) ? Identity::query()->find($id) : null;
    }

    public function joinsExistingIdentity(): bool
    {
        return ($this->state()['identity_id'] ?? null) !== null;
    }

    /**
     * Guarda o **hash** da senha escolhida, nunca a senha.
     */
    public function rememberNewPassword(#[\SensitiveParameter] string $plainPassword): void
    {
        $this->update([
            'password_hash' => Hash::make($plainPassword),
            'identity_verified' => false,
        ]);
    }

    /**
     * A pessoa provou saber a senha da identidade que já existe. Nada de senha fica guardado.
     */
    public function markIdentityVerified(): void
    {
        $this->update(['identity_verified' => true, 'password_hash' => null]);
    }

    /**
     * Hash da senha nova, para criar a identidade na última etapa. Null no caminho da conta irmã,
     * em que a senha já está gravada na identidade.
     */
    public function newPasswordHash(): ?string
    {
        $hash = $this->state()['password_hash'] ?? null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    /**
     * A etapa da senha está resolvida: ou a pessoa escolheu uma (identidade nova), ou provou a da
     * identidade que já existe.
     */
    public function passwordIsSettled(): bool
    {
        return $this->joinsExistingIdentity()
            ? ($this->state()['identity_verified'] ?? false) === true
            : $this->newPasswordHash() !== null;
    }

    public function hasStarted(): bool
    {
        return $this->profile() !== null && $this->email() !== null;
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $state = $this->session->get(self::KEY);

        if (! is_array($state)) {
            return [];
        }

        $startedAt = $state['started_at'] ?? null;

        if (! is_int($startedAt) || $startedAt < now()->subMinutes(self::TTL_MINUTES)->getTimestamp()) {
            $this->clear();

            return [];
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(array $attributes): void
    {
        $state = $this->state();

        if ($state === []) {
            return;
        }

        $this->session->put(self::KEY, [...$state, ...$attributes]);
    }
}
