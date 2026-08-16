<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorChallengeService;
use App\Services\TwoFactorService;
use App\Support\SanctumMobileToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled.',
            ], 422);
        }

        $secret = $this->twoFactorService->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'otpauth_uri' => $this->twoFactorService->otpauthUri($user, $secret),
            ],
            'message' => 'Scan the secret with your authenticator app, then confirm with a code.',
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        if ($this->challengeService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled.',
            ], 422);
        }

        if ($user->two_factor_secret === null) {
            return response()->json([
                'success' => false,
                'message' => 'Enable two-factor authentication before confirming.',
            ], 422);
        }

        if (! $this->twoFactorService->verifyTotp($user, $request->string('code')->toString())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication code.',
            ], 422);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(
                $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
            ),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
            'message' => 'Two-factor authentication enabled.',
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $this->challengeService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled.',
            ], 422);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
            ], 422);
        }

        if (! $this->twoFactorService->verifyTotp($user, $request->string('code')->toString())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication code.',
            ], 422);
        }

        $this->twoFactorService->clearTwoFactor($user);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled.',
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $this->challengeService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled.',
            ], 422);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
            ], 422);
        }

        if (! $this->twoFactorService->verifyTotp($user, $request->string('code')->toString())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication code.',
            ], 422);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $this->twoFactorService->persistRecoveryCodeHashes(
            $user,
            $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
            'message' => 'Recovery codes regenerated.',
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'code' => 'required_without:recovery_code|nullable|string|size:6',
            'recovery_code' => 'required_without:code|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $challengeToken = $request->string('challenge_token')->toString();
        $user = $this->challengeService->resolveUser($challengeToken);

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired challenge token.',
            ], 422);
        }

        if (! $this->challengeService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled for this account.',
            ], 422);
        }

        $code = $request->input('code');
        $recoveryCode = $request->input('recovery_code');

        if (! $this->challengeService->verifyChallenge($user, is_string($code) ? $code : null, is_string($recoveryCode) ? $recoveryCode : null)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication code.',
            ], 422);
        }

        $this->challengeService->forgetChallenge($challengeToken);

        return SanctumMobileToken::loginResponse($user, 'Login successful');
    }
}
