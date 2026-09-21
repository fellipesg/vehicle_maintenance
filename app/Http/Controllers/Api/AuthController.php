<?php

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationSource;
use App\Events\UserRegistered;
use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\TenantService;
use App\Support\ApiResponse;
use App\Support\SanctumMobileToken;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

#[Group('Authentication', weight: 0)]
class AuthController extends Controller
{
    use ResolvesPagination;

    /**
     * Register a new user.
     *
     * Returns a Bearer token on success, or a 2FA challenge when two-factor is enabled.
     */
    #[Endpoint(title: 'Register')]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => 'user',
                'phone' => $request->phone,
                'postal_code' => $request->postal_code,
                'street' => $request->street,
                'number' => $request->number,
                'complement' => $request->complement,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country ?? 'Brasil',
            ]);

            (new TenantService)->createForUser($user);

            return $user->refresh();
        });

        UserRegistered::dispatch($user, RegistrationSource::Api);

        if ($twoFactorResponse = $this->maybeIssueTwoFactorChallenge($user)) {
            return $twoFactorResponse;
        }

        $token = SanctumMobileToken::issue($user);

        return ApiResponse::created([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'User registered successfully');
    }

    /**
     * Login with email and password.
     *
     * Returns a Bearer token on success, or a 2FA challenge when two-factor is enabled.
     */
    #[Endpoint(title: 'Login')]
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::validate($request->only('email', 'password'))) {
            return ApiResponse::error('Invalid login credentials', 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if ($request->filled('portal') && ! $this->userMatchesPortal($user, $request->string('portal')->toString())) {
            return ApiResponse::error('This account does not have access to this portal.', 403);
        }

        if ($twoFactorResponse = $this->maybeIssueTwoFactorChallenge($user)) {
            return $twoFactorResponse;
        }

        return SanctumMobileToken::loginResponse($user);
    }

    #[Endpoint(title: 'Logout')]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(message: 'Logged out successfully');
    }

    #[Endpoint(title: 'Current user')]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('currentVehicles');

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Get the OAuth provider authorization URL (browser flow).
     */
    #[Group('OAuth (Advanced)', 'Optional browser-based OAuth for mobile/web clients. Email/password login is the primary method.', weight: 90)]
    #[Endpoint(
        title: 'OAuth redirect URL',
        description: 'Returns a URL to open in a browser. After authorization, the provider redirects to the callback endpoint.',
    )]
    public function redirectToProvider(string $provider): JsonResponse
    {
        $validProviders = ['google', 'twitter', 'facebook'];

        if (! in_array($provider, $validProviders)) {
            return ApiResponse::error('Invalid provider', 400);
        }

        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");
        $redirectUri = config("services.{$provider}.redirect");

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            return ApiResponse::error(
                ucfirst($provider).' OAuth credentials not configured.',
                500,
            );
        }

        try {
            $redirectUrl = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl($redirectUri)
                ->redirect()
                ->getTargetUrl();

            return ApiResponse::success([
                'redirect_url' => $redirectUrl,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(
                app()->hasDebugModeEnabled()
                    ? 'Error generating OAuth URL: '.$e->getMessage()
                    : 'Unable to generate OAuth URL.',
                500,
            );
        }
    }

    /**
     * Handle OAuth provider callback and issue a Bearer token.
     */
    #[Group('OAuth (Advanced)', 'Optional browser-based OAuth for mobile/web clients. Email/password login is the primary method.', weight: 90)]
    #[Endpoint(
        title: 'OAuth callback',
        description: 'Called by the OAuth provider after user authorization. Returns a Bearer token or 2FA challenge.',
    )]
    public function handleProviderCallback(string $provider): JsonResponse
    {
        $validProviders = ['google', 'twitter', 'facebook'];

        if (! in_array($provider, $validProviders)) {
            return ApiResponse::error('Invalid provider', 400);
        }

        try {
            $redirectUri = config("services.{$provider}.redirect");

            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl($redirectUri)
                ->user();

            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            $isNewUser = false;

            if (! $user) {
                $user = User::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'avatar' => $socialUser->getAvatar(),
                    ]);
                } else {
                    $user = DB::transaction(function () use ($socialUser, $provider): User {
                        $user = User::create([
                            'name' => $socialUser->getName(),
                            'email' => $socialUser->getEmail(),
                            'provider' => $provider,
                            'provider_id' => $socialUser->getId(),
                            'avatar' => $socialUser->getAvatar(),
                            'password' => Hash::make(uniqid()),
                        ]);
                        (new TenantService)->createForUser($user);

                        return $user->refresh();
                    });
                    $isNewUser = true;
                }
            } elseif ($socialUser->getAvatar() && $user->avatar !== $socialUser->getAvatar()) {
                $user->update(['avatar' => $socialUser->getAvatar()]);
            }

            if ($isNewUser) {
                UserRegistered::dispatch($user, RegistrationSource::Oauth);
            }

            if ($twoFactorResponse = $this->maybeIssueTwoFactorChallenge($user)) {
                return $twoFactorResponse;
            }

            return SanctumMobileToken::loginResponse($user);
        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                app()->hasDebugModeEnabled()
                    ? 'Error authenticating with '.$provider.': '.$e->getMessage()
                    : 'Unable to authenticate with the selected provider.',
                500,
            );
        }
    }

    private function maybeIssueTwoFactorChallenge(User $user): ?JsonResponse
    {
        if (class_exists(\App\Services\TwoFactorChallengeService::class)) {
            $challenge = app(\App\Services\TwoFactorChallengeService::class);
            if ($challenge->isEnabled($user)) {
                return $challenge->issuePendingResponse($user);
            }
        }

        return null;
    }

    private function userMatchesPortal(User $user, string $portal): bool
    {
        return match ($portal) {
            'admin' => $user->isAdmin(),
            'lojista' => $user->isGarage(),
            'usuario' => $user->isUser(),
            'oficina' => $user->isWorkshop(),
            default => false,
        };
    }
}
