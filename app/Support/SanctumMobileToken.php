<?php

namespace App\Support;

use App\Http\Resources\Api\V1\UserResource;
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
        $user->loadMissing('currentVehicles');

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => self::issue($user),
            'token_type' => 'Bearer',
        ], $message);
    }
}
