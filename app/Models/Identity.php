<?php

namespace App\Models;

use App\Enums\Portal;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Identidade: o e-mail, a senha e o 2FA de uma pessoa. Acima das contas, não no lugar delas.
 *
 * Cada papel (proprietário, lojista, oficina) continua sendo sua própria linha de `users`, com o
 * `tenant_id` dela, a oficina dela, a loja dela — nada muda na autorização. As linhas de `users`
 * que compartilham a identidade são **contas irmãs**: a mesma pessoa, o mesmo e-mail, a mesma
 * senha, um papel cada (`unique(identity_id, user_type)`).
 *
 * O guard `web` continua devolvendo um App\Models\User, e não uma identidade: é o `tenant_id` do
 * usuário que toda policy compara. A identidade entra só na porta de entrada — o login escolhe a
 * irmã do portal pedido — e no broker de senha (`config/auth.php`, provider `identities`).
 *
 * É Authenticatable para servir de provider ao broker de senha (o EloquentUserProvider trabalha
 * com esse contrato), não para ser o usuário logado.
 */
class Identity extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\IdentityFactory> */
    use HasFactory, Notifiable;

    private ?string $resolvedName = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * As contas irmãs desta identidade: uma por papel.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * O "Lembrar de mim" é de cada conta (`users.remember_token`), não da identidade: o cookie
     * recaller guarda o id do usuário, então é a linha dele que o provider do guard confere.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    /**
     * A conta desta identidade que atende o portal pedido, ou null quando ela não tem esse papel.
     * O Painel admin não é um papel: é a área extra de quem tem `is_admin`, então a conta de admin
     * é a irmã com a flag ligada (ver App\Enums\Portal::accessibleBy).
     */
    public function accountFor(Portal $portal): ?User
    {
        $accounts = $this->users()->get();

        return $portal === Portal::Admin
            ? $accounts->firstWhere(fn (User $user): bool => $user->isAdmin())
            : $accounts->firstWhere('user_type', $portal->value);
    }

    /**
     * Portais que esta identidade abre, na ordem das telas de entrada. É o que o login mostra a
     * quem acerta a senha na porta errada.
     *
     * @return list<Portal>
     */
    public function accessiblePortals(): array
    {
        $portals = [];

        foreach ([Portal::Owner, Portal::Dealer, Portal::Workshop, Portal::Admin] as $portal) {
            if ($this->accountFor($portal) !== null) {
                $portals[] = $portal;
            }
        }

        return $portals;
    }

    /**
     * Troca o e-mail da identidade e das contas irmãs na mesma transação. O e-mail é da identidade;
     * `users.email` é cópia desnormalizada, lida pelo provider do guard, pelas 10 buscas por
     * endereço e por toda tela — deixar as duas pontas fora de sincronia é como uma conta irmã
     * passa a não entrar mais.
     */
    public function changeEmailTo(string $email): void
    {
        DB::transaction(function () use ($email): void {
            $this->forceFill(['email' => $email])->save();
            $this->users()->update(['email' => $email]);
        });
    }

    /**
     * Senha nova para a identidade inteira: ela é compartilhada pelas contas irmãs, então a troca
     * vale para todas. O `remember_token` de cada irmã é rodado junto, para derrubar o "Lembrar de
     * mim" de todos os aparelhos, e os tokens do app de todas elas saem — quem redefine a senha
     * costuma estar tirando alguém da conta. `$keepTokenId` poupa o token da própria requisição,
     * que é o que o PUT /api/v1/me/password faz para o app não se deslogar ao salvar.
     */
    public function changePasswordTo(
        #[\SensitiveParameter] string $plainPassword,
        int|string|null $keepTokenId = null,
    ): void {
        DB::transaction(function () use ($plainPassword, $keepTokenId): void {
            $this->forceFill(['password' => $plainPassword])->save();

            foreach ($this->users()->get() as $user) {
                $user->forceFill(['remember_token' => Str::random(60)])->save();

                $user->tokens()
                    ->when($keepTokenId !== null, fn ($query) => $query->whereKeyNot($keepTokenId))
                    ->delete();
            }
        });
    }

    /**
     * Nome da pessoa, lido da primeira conta dela: a identidade guarda credencial, não cadastro.
     * Quem usa é o e-mail de redefinição de senha (App\Notifications\ResetPasswordNotification),
     * que cumprimenta pelo primeiro nome — o mesmo em qualquer papel, porque é a mesma pessoa.
     */
    public function getNameAttribute(): ?string
    {
        return $this->resolvedName ??= $this->users()->orderBy('id')->value('name');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function hasPendingTwoFactorSetup(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at === null;
    }

    /**
     * @return list<string>
     */
    public function twoFactorRecoveryCodeHashes(): array
    {
        if ($this->two_factor_recovery_codes === null || $this->two_factor_recovery_codes === '') {
            return [];
        }

        $decoded = json_decode($this->two_factor_recovery_codes, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Link de redefinição de senha em pt-BR, no chrome de e-mail do projeto. A senha é da
     * identidade, então é ela que recebe o link — e não uma das contas irmãs.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
