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
 * Nunca usa User::delete(), nem para conta que parece vazia. Dez tabelas apontam para `users` em
 * cascata — manutenções, vínculos de veículo, oficina, loja, a declaração da consignação
 * (`vehicle_consignments.garage_user_id`, com a trilha de autorização do proprietário) e até o
 * opt-out de mensagens, cuja remoção faria a pessoa voltar a receber contato. Conferir uma a uma
 * seria frágil: a próxima migration com cascade reabriria o buraco em silêncio. Então a conta é
 * neutralizada e anonimizada pelo mesmo DeleteUserAccount da exclusão de conta, e a linha fica.
 */
class PurgeDevAccounts extends Command
{
    protected $signature = 'users:purge-dev-accounts {--dry-run : Só mostra o que seria feito}';

    protected $description = 'Neutraliza e anonimiza em produção as contas de desenvolvimento (@vehicle-maintenance.test)';

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
            $rows[] = [
                $account->email,
                $account->isAdmin() ? 'sim' : 'não',
                $this->belongingsOf($account),
            ];

            if ($dryRun) {
                continue;
            }

            // A credencial primeiro: mesmo que a anonimização falhe, a porta já está fechada.
            $this->revokeAccess($account);

            $deleteUserAccount->handle($account);
        }

        $this->table(['E-mail', 'Era admin', 'Mantido vinculado'], $rows);

        if ($dryRun) {
            $this->warn('Simulação: nada foi alterado. Rode sem --dry-run para aplicar.');

            return self::SUCCESS;
        }

        $this->info($accounts->count().' conta(s) neutralizada(s) e anonimizada(s). Nenhuma linha foi apagada.');

        return self::SUCCESS;
    }

    /**
     * Só para o relatório: o que continua no banco depois da anonimização.
     */
    private function belongingsOf(User $user): string
    {
        $belongings = [];

        if (($count = Maintenance::query()->where('user_id', $user->id)->count()) > 0) {
            $belongings[] = "{$count} manutenção(ões)";
        }

        if ($user->workshop()->exists()) {
            $belongings[] = 'oficina';
        }

        if ($user->garage()->exists()) {
            $belongings[] = 'loja';
        }

        return $belongings === [] ? '—' : implode(', ', $belongings);
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
