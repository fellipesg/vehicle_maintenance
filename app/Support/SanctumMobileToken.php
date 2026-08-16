<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class SanctumMobileToken
{
    public const TOKEN_NAME = 'mobile';

    /**
     * @var list<string>
     */
    public const ABILITIES = [
        'profile:read',
        'profile:write',
        'vehicles:read',
        'vehicles:write',
        'maintenances:read',
        'maintenances:write',
        'invoices:write',
        'workshops:read',
        'workshops:write',
        'fcm:write',
    ];

    public static function issue(User $user): string
    {
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        return $user->createToken(
            self::TOKEN_NAME,
            self::ABILITIES,
            now()->addDays(30),
        )->plainTextToken;
    }

    public static function loginResponse(User $user, string $message = 'Login successful'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => self::issue($user),
                'token_type' => 'Bearer',
            ],
            'message' => $message,
        ]);
    }
}
