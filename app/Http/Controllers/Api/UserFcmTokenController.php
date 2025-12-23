<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserFcmTokenController extends Controller
{
    /**
     * Register or update FCM token
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:500',
            'device_type' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        
        // Check if token already exists for this user
        $existingToken = UserFcmToken::where('token', $request->token)->first();
        
        if ($existingToken) {
            // Update if belongs to different user
            if ($existingToken->user_id !== $user->id) {
                $existingToken->update([
                    'user_id' => $user->id,
                    'device_type' => $request->device_type ?? 'android',
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $existingToken,
                'message' => 'FCM token updated successfully',
            ]);
        }

        // Create new token
        $fcmToken = UserFcmToken::create([
            'user_id' => $user->id,
            'token' => $request->token,
            'device_type' => $request->device_type ?? 'android',
        ]);

        return response()->json([
            'success' => true,
            'data' => $fcmToken,
            'message' => 'FCM token registered successfully',
        ], 201);
    }

    /**
     * Remove FCM token (by token value)
     */
    public function destroy(string $token): JsonResponse
    {
        $user = auth()->user();
        
        $fcmToken = UserFcmToken::where('token', $token)
            ->where('user_id', $user->id)
            ->first();

        if (!$fcmToken) {
            return response()->json([
                'success' => false,
                'message' => 'FCM token not found',
            ], 404);
        }

        $fcmToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'FCM token removed successfully',
        ]);
    }

    /**
     * Get all FCM tokens for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = UserFcmToken::where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }
}
