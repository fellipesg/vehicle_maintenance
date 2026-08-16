<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorChallengeService
{
    private const CHALLENGE_TTL_MINUTES = 10;

    public function __construct(private TwoFactorService $twoFactorService) {}

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    public function issuePendingResponse(User $user): JsonResponse
    {
        $challengeToken = Str::random(64);

        Cache::put(
            $this->cacheKey($challengeToken),
            $user->id,
            now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
        );

        return response()->json([
            'success' => true,
            'requires_two_factor' => true,
            'challenge_token' => $challengeToken,
            'message' => 'Two-factor authentication required.',
        ]);
    }

    public function resolveUser(string $challengeToken): ?User
    {
        $userId = Cache::get($this->cacheKey($challengeToken));

        if ($userId === null) {
            return null;
        }

        return User::query()->find($userId);
    }

    public function forgetChallenge(string $challengeToken): void
    {
        Cache::forget($this->cacheKey($challengeToken));
    }

    public function verifyChallenge(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if ($code !== null && $code !== '') {
            return $this->twoFactorService->verifyTotp($user, $code);
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            $result = $this->twoFactorService->consumeRecoveryCode($user, $recoveryCode);

            if ($result['matched']) {
                $this->twoFactorService->persistRecoveryCodeHashes($user, $result['remaining_hashes']);

                return true;
            }
        }

        return false;
    }

    private function cacheKey(string $challengeToken): string
    {
        return 'two_factor_challenge:'.$challengeToken;
    }
}
