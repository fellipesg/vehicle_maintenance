<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use App\Models\User;
use App\Services\User\DeleteUserAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Tira de produção as contas de desenvolvimento do DevPortalUsersSeeder.
 *
 * As senhas dessas contas estão no repositório público (DevPortalUsersSeeder::PASSWORD) e uma
 * delas é admin, então qualquer cópia que tenha escapado para produção — banco restaurado de um
 * dump local, APP_ENV errado numa fase inicial — é credencial conhecida com acesso total.
 *
 * O domínio .test é reservado por RFC 2606: nenhum usuário real tem e-mail assim, então o filtro
 * é seguro.
 *
 * Toda conta encontrada perde a senha, o selo de admin e as sessões. Depois disso, a que não tem
 * nada vinculado é removida; a que já gerou histórico é anonimizada pelo mesmo DeleteUserAccount
 * da exclusão de conta, porque apagar a linha cascatearia em maintenances.user_id e levaria junto
 * registros do chassi.
 */
class PurgeDevAccounts extends Command
{
    protected $signature = 'users:purge-dev-accounts {--dry-run : Só mostra o que seria feito}';

    protected $description = 'Remove de produção as contas de desenvolvimento (@vehicle-maintenance.test)';

    public const EMAIL_PATTERN = '%@vehicle-maintenance.test';

    public function handle(DeleteUserAccount $deleteUserAccount): int
    {
        $accounts = User::query()
            ->where('email', 'like', self::EMAIL_PATTERN)
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('Nenhuma conta de desenvolvimento encontrada.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($accounts as $account) {
            $belongings = $this->belongingsOf($account);
            $action = $belongings === [] ? 'remover' : 'anonimizar';

            $rows[] = [
                $account->email,
                $account->isAdmin() ? 'sim' : 'não',
                $belongings === [] ? '—' : implode(', ', $belongings),
                $dryRun ? "{$action} (simulação)" : $action,
            ];

            if ($dryRun) {
                continue;
            }

            // Primeiro a credencial: mesmo que o resto falhe, a porta já está fechada.
            $this->revokeAccess($account);

            if ($belongings === []) {
                $account->delete();

                continue;
            }

            $deleteUserAccount->handle($account);
        }

        $this->table(['E-mail', 'Era admin', 'Vinculado', 'Ação'], $rows);

        if ($dryRun) {
            $this->warn('Simulação: nada foi alterado. Rode sem --dry-run para aplicar.');

            return self::SUCCESS;
        }

        $this->info($accounts->count().' conta(s) de desenvolvimento tratada(s).');

        return self::SUCCESS;
    }

    /**
     * O que impede a remoção da linha.
     *
     * @return list<string>
     */
    private function belongingsOf(User $user): array
    {
        $belongings = [];

        if (($count = Maintenance::query()->where('user_id', $user->id)->count()) > 0) {
            $belongings[] = "{$count} manutenção(ões)";
        }

        if (($count = $user->vehicles()->count()) > 0) {
            $belongings[] = "{$count} veículo(s)";
        }

        if ($user->workshop()->exists()) {
            $belongings[] = 'oficina';
        }

        if ($user->garage()->exists()) {
            $belongings[] = 'loja';
        }

        return $belongings;
    }

    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        $user->fcmTokens()->delete();

        $user->forceFill([
            'password' => Str::password(40),
            'is_admin' => false,
            'subscription_active' => false,
            'remember_token' => null,
        ])->save();
    }
}
