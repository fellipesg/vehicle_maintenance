<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

#[Group('Password', weight: 9)]
class PasswordController extends Controller
{
    /**
     * Send the password reset link.
     *
     * The link opens the web page that actually changes the password, the same one the portals use.
     */
    #[Endpoint(
        title: 'Request password reset link',
        description: 'Public endpoint. Answers the same message whether or not the e-mail has an account.',
    )]
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // Conta inexistente, link pedido há menos de 1 minuto ou link enviado: a resposta é a
        // mesma, para não revelar quais e-mails estão cadastrados. A mensagem vem do controller
        // do portal de propósito — as duas superfícies têm de responder exatamente igual.
        Password::sendResetLink(['email' => $request->validated('email')]);

        return ApiResponse::success(
            message: PasswordResetController::LINK_REQUESTED_MESSAGE,
        );
    }

    /**
     * Change the password of the authenticated account.
     *
     * The other devices are signed out; the token used in this request keeps working.
     */
    #[Endpoint(title: 'Change password')]
    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Sanctum::actingAs (e o cliente web por sessão) usa um TransientToken, que não está na
        // tabela e não tem chave; só um token de verdade pode ser poupado.
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof Model ? $currentToken->getKey() : null;

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            // Desliga o "Lembrar de mim" das sessões web, como a troca pelo portal.
            'remember_token' => Str::random(60),
        ])->save();

        // Quem troca a senha costuma estar tirando outro aparelho da conta, então os outros
        // tokens saem. O desta requisição fica, senão o app se deslogaria sozinho ao salvar.
        $user->tokens()
            ->when(
                $currentTokenId !== null,
                fn ($query) => $query->whereKeyNot($currentTokenId),
            )
            ->delete();

        return ApiResponse::success(
            message: 'Senha alterada. Os outros aparelhos foram desconectados.',
        );
    }
}
