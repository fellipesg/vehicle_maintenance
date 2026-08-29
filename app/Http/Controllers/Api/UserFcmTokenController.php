<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFcmTokenRequest;
use App\Http\Resources\Api\V1\UserFcmTokenResource;
use App\Models\UserFcmToken;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('FCM Tokens', weight: 25)]
class UserFcmTokenController extends Controller
{
    use ResolvesPagination;

    public function store(StoreFcmTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $existingToken = UserFcmToken::where('token', $request->token)->first();

        if ($existingToken) {
            Gate::authorize('update', $existingToken);

            $existingToken->update([
                'device_type' => $request->device_type ?? $existingToken->device_type ?? 'android',
            ]);

            return ApiResponse::success(new UserFcmTokenResource($existingToken), 'FCM token updated successfully');
        }

        $fcmToken = UserFcmToken::create([
            'user_id' => $user->id,
            'token' => $request->token,
            'device_type' => $request->device_type ?? 'android',
        ]);

        return ApiResponse::created(new UserFcmTokenResource($fcmToken), 'FCM token registered successfully');
    }

    public function destroy(string $token): JsonResponse
    {
        $fcmToken = UserFcmToken::where('token', $token)->firstOrFail();

        Gate::authorize('delete', $fcmToken);

        $fcmToken->delete();

        return ApiResponse::success(message: 'FCM token removed successfully');
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', UserFcmToken::class);

        $user = $request->user();
        $tokens = UserFcmToken::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($tokens, UserFcmTokenResource::class);
    }
}
