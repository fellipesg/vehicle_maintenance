<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FcmService;
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
            'user_type' => 'required|in:user,workshop',
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
            'user_type' => $request->user_type,
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

        // Send welcome notification (async, don't wait for it)
        try {
            $fcmService = new FcmService();
            $fcmService->sendToUser(
                $user->id,
                'Bem-vindo ao Vehicle Maintenance! 🚗',
                "Olá {$user->name}! Sua conta foi criada com sucesso. Comece a gerenciar suas manutenções!",
                [
                    'type' => 'welcome',
                    'user_id' => (string)$user->id,
                ]
            );
        } catch (\Exception $e) {
            // Don't fail registration if notification fails
            \Log::warning('Failed to send welcome notification: ' . $e->getMessage());
        }

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

        // Send welcome notification (async, don't wait for it)
        try {
            $fcmService = new FcmService();
            $fcmService->sendToUser(
                $user->id,
                'Bem-vindo de volta! 👋',
                "Olá {$user->name}! Você entrou no Vehicle Maintenance.",
                [
                    'type' => 'welcome',
                    'user_id' => (string)$user->id,
                ]
            );
        } catch (\Exception $e) {
            // Don't fail login if notification fails
            \Log::warning('Failed to send welcome notification: ' . $e->getMessage());
        }

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

        // Check if OAuth credentials are configured
        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");
        $redirectUri = config("services.{$provider}.redirect");

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($provider) . ' OAuth credentials not configured. Please set ' . strtoupper($provider) . '_CLIENT_ID, ' . strtoupper($provider) . '_CLIENT_SECRET, and ' . strtoupper($provider) . '_REDIRECT_URI in your .env file.',
                'error_code' => 'OAUTH_NOT_CONFIGURED',
            ], 500);
        }

        try {
            // Get the redirect URI from config
            $redirectUri = config("services.{$provider}.redirect");
            
            // Use Socialite with explicit redirect URI to ensure consistency
            $redirectUrl = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl($redirectUri)
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'success' => true,
                'data' => [
                    'redirect_url' => $redirectUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating OAuth URL: ' . $e->getMessage(),
                'error_code' => 'OAUTH_ERROR',
            ], 500);
        }
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
            // Use the same redirect URI from config that was used in the initial authorization URL
            // This is critical - Google requires the redirect_uri to match exactly
            $redirectUri = config("services.{$provider}.redirect");
            
            // Log for debugging
            \Log::info('OAuth callback', [
                'provider' => $provider,
                'redirect_uri' => $redirectUri,
                'request_url' => request()->fullUrl(),
                'has_code' => request()->has('code'),
            ]);
            
            // Use Socialite with the same redirect URI from config
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl($redirectUri)
                ->user();

            // Find or create user
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            $isNewUser = false;
            
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
                    $isNewUser = true;
                }
            } else {
                // Update avatar if changed
                if ($socialUser->getAvatar() && $user->avatar !== $socialUser->getAvatar()) {
                    $user->update(['avatar' => $socialUser->getAvatar()]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // Send welcome notification (async, don't wait for it)
            try {
                $fcmService = new FcmService();
                if ($isNewUser) {
                    $fcmService->sendToUser(
                        $user->id,
                        'Bem-vindo ao Vehicle Maintenance! 🚗',
                        "Olá {$user->name}! Sua conta foi criada com sucesso. Comece a gerenciar suas manutenções!",
                        [
                            'type' => 'welcome',
                            'user_id' => (string)$user->id,
                        ]
                    );
                } else {
                    $fcmService->sendToUser(
                        $user->id,
                        'Bem-vindo de volta! 👋',
                        "Olá {$user->name}! Você entrou no Vehicle Maintenance.",
                        [
                            'type' => 'welcome',
                            'user_id' => (string)$user->id,
                        ]
                    );
                }
            } catch (\Exception $e) {
                // Don't fail login if notification fails
                \Log::warning('Failed to send welcome notification: ' . $e->getMessage());
            }

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
