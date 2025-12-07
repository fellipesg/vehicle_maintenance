<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'postal_code' => $request->postal_code,
            'street' => $request->street,
            'number' => $request->number,
            'complement' => $request->complement,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country ?? 'Brasil',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'message' => 'User registered successfully',
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('currentVehicles');

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Redirect to SSO provider
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        $validProviders = ['google', 'twitter', 'facebook'];
        
        if (!in_array($provider, $validProviders)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid provider',
            ], 400);
        }

        $redirectUrl = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

        return response()->json([
            'success' => true,
            'data' => [
                'redirect_url' => $redirectUrl,
            ],
        ]);
    }

    /**
     * Handle SSO callback
     */
    public function handleProviderCallback(string $provider): JsonResponse
    {
        $validProviders = ['google', 'twitter', 'facebook'];
        
        if (!in_array($provider, $validProviders)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid provider',
            ], 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Find or create user
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if (!$user) {
                // Check if user exists with this email
                $user = User::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    // Update existing user with provider info
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'avatar' => $socialUser->getAvatar(),
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $socialUser->getName(),
                        'email' => $socialUser->getEmail(),
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'avatar' => $socialUser->getAvatar(),
                        'password' => Hash::make(uniqid()), // Random password for SSO users
                    ]);
                }
            } else {
                // Update avatar if changed
                if ($socialUser->getAvatar() && $user->avatar !== $socialUser->getAvatar()) {
                    $user->update(['avatar' => $socialUser->getAvatar()]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
                'message' => 'Login successful',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error authenticating with ' . $provider . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}
