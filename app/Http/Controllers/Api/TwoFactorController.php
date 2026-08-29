<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChallengeTwoFactorRequest;
use App\Http\Requests\Api\V1\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\V1\DisableTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorChallengeService;
use App\Services\TwoFactorService;
use App\Support\ApiResponse;
use App\Support\SanctumMobileToken;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

#[Group('Two-Factor Authentication', weight: 8)]
class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private TwoFactorChallengeService $challengeService,
    ) {}

    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->challengeService->isEnabled($user)) {
            return ApiResponse::error('Two-factor authentication is already enabled.', 422);
        }

        $secret = $this->twoFactorService->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return ApiResponse::success([
            'secret' => $secret,
            'otpauth_uri' => $this->twoFactorService->otpauthUri($user, $secret),
        ], 'Scan the secret with your authenticator app, then confirm with a code.');
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->challengeService->isEnabled($user)) {
            return ApiResponse::error('Two-factor authentication is already enabled.', 422);
        }

        if ($user->two_factor_secret === null) {
            return ApiResponse::error('Enable two-factor authentication before confirming.', 422);
        }

        if (! $this->twoFactorService->verifyTotp($user, $request->string('code')->toString())) {
            return ApiResponse::error('Invalid authentication code.', 422);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(
                $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
            ),
        ])->save();

        return ApiResponse::success([
            'recovery_codes' => $recoveryCodes,
        ], 'Two-factor authentication enabled.');
    }

    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->challengeService->isEnabled($user)) {
            return ApiResponse::error('Two-factor authentication is not enabled.', 422);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return ApiResponse::error('Invalid password.', 422);
        }

        if (! $this->twoFactorService->verifyTotp($user, $request->string('code')->toString())) {
            return ApiResponse::error('Invalid authentication code.', 422);
        }

        $this->twoFactorService->clearTwoFactor($user);

        return ApiResponse::success(message: 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(DisableTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->challengeService->isEnabled($user)) {
            return ApiResponse::error('Two-factor authentication is not enabled.', 422);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return ApiResponse::error('Invalid password.', 422);
        }

        if (! $this->twoFactorService->verifyTotp($user, $request->string('code')->toString())) {
            return ApiResponse::error('Invalid authentication code.', 422);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $this->twoFactorService->persistRecoveryCodeHashes(
            $user,
            $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
        );

        return ApiResponse::success([
            'recovery_codes' => $recoveryCodes,
        ], 'Recovery codes regenerated.');
    }

    #[Endpoint(
        title: 'Complete 2FA challenge',
        description: 'Public endpoint used after login/register when 2FA is enabled. Provide `challenge_token` from the pending response plus either a TOTP `code` or a `recovery_code`.',
    )]
    public function challenge(ChallengeTwoFactorRequest $request): JsonResponse
    {
        $challengeToken = $request->string('challenge_token')->toString();
        $user = $this->challengeService->resolveUser($challengeToken);

        if ($user === null) {
            return ApiResponse::error('Invalid or expired challenge token.', 422);
        }

        if (! $this->challengeService->isEnabled($user)) {
            return ApiResponse::error('Two-factor authentication is not enabled for this account.', 422);
        }

        $code = $request->input('code');
        $recoveryCode = $request->input('recovery_code');

        if (! $this->challengeService->verifyChallenge($user, is_string($code) ? $code : null, is_string($recoveryCode) ? $recoveryCode : null)) {
            return ApiResponse::error('Invalid authentication code.', 422);
        }

        $this->challengeService->forgetChallenge($challengeToken);

        return SanctumMobileToken::loginResponse($user, 'Login successful');
    }
}
