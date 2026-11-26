<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\EndsDeletedAccountSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreAccountDeletionRequest;
use App\Mail\AccountDeletionRequestMail;
use App\Models\User;
use App\Services\User\DeleteUserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exclusão de conta pedida de fora do app, exigida pelo Google Play: a página é pública e o link
 * aparece na ficha da loja.
 *
 * Quem prova a posse do e-mail é a própria caixa de entrada — o formulário só dispara um link
 * assinado e temporário. A resposta é sempre a mesma, exista a conta ou não, para a página não
 * virar um conferidor de cadastros.
 *
 * A exclusão em si é a mesma do app: App\Services\User\DeleteUserAccount anonimiza os dados
 * pessoais e preserva veículos, placas e manutenções, que pertencem ao chassi.
 *
 * Com identidade compartilhada o endereço pode ter uma conta por papel. A página é pública — não há
 * sessão para dizer de qual papel se trata —, então o e-mail leva **um link por perfil**, nomeado,
 * e a escolha é de quem abre a caixa de entrada. Cada link apaga só o seu perfil; os outros ficam
 * (ver DeleteUserAccount). Mandar um link só, para a primeira conta que o e-mail achasse, apagaria
 * silenciosamente o papel errado.
 *
 * O link pode ser aberto no mesmo navegador em que a pessoa está logada, então a sessão dela
 * também sai daqui (EndsDeletedAccountSession).
 */
class AccountDeletionController extends Controller
{
    use EndsDeletedAccountSession;

    private const LINK_MINUTES = 60;

    public function show(): View
    {
        return view('legal.account-deletion', [
            'supportEmail' => config('legal.support_email'),
        ]);
    }

    public function request(StoreAccountDeletionRequest $request): RedirectResponse
    {
        if (blank($request->input('website'))) {
            $accounts = User::query()
                ->where('email', $request->validated('email'))
                ->orderBy('id')
                ->get();

            if ($accounts->isNotEmpty()) {
                Mail::to((string) $accounts->first()->email)->send(new AccountDeletionRequestMail(
                    confirmations: $accounts
                        ->map(fn (User $user): array => [
                            'label' => $user->typeLabel(),
                            'url' => $this->confirmationUrl($user),
                        ])
                        ->values()
                        ->all(),
                    expiresInMinutes: self::LINK_MINUTES,
                ));
            }
        }

        // Mesma resposta para e-mail cadastrado e não cadastrado.
        return redirect()
            ->route('account-deletion.show')
            ->with('status', 'Se houver uma conta com esse e-mail, enviamos o link de confirmação. Ele vale por 1 hora.');
    }

    public function confirm(Request $request, User $user): View
    {
        $this->assertLinkMatchesAccount($request, $user);

        return view('legal.account-deletion-confirm', [
            'user' => $user,
            'confirmationUrl' => $request->fullUrl(),
            // Quantos outros perfis o mesmo e-mail tem: a página diz qual está sendo excluído e
            // que os demais continuam.
            'otherProfileLabels' => $user->siblingAccounts()
                ->map(fn (User $sibling): string => $sibling->typeLabel())
                ->values()
                ->all(),
        ]);
    }

    public function destroy(Request $request, User $user, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        $this->assertLinkMatchesAccount($request, $user);

        $deleteUserAccount->handle($user);
        $this->endWebSessionOf($request, $user);

        return redirect()
            ->route('account-deletion.show')
            ->with('status', 'Conta excluída. Seus dados pessoais foram apagados.');
    }

    /**
     * O hash prende o link ao e-mail que o pediu: trocar de endereço — ou já ter sido excluído,
     * porque a exclusão troca o e-mail por um inutilizável — invalida o link na hora.
     */
    private function assertLinkMatchesAccount(Request $request, User $user): void
    {
        if (! hash_equals(sha1($user->email), (string) $request->query('hash'))) {
            throw new HttpException(403, 'Este link de exclusão não vale mais.');
        }
    }

    private function confirmationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->addMinutes(self::LINK_MINUTES),
            ['user' => $user->id, 'hash' => sha1($user->email)],
        );
    }
}
