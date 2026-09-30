<?php

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationSource;
use App\Events\UserRegistered;
use App\Exceptions\InvalidAppleIdentityTokenException;
use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AppleLoginRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\AppleIdentityTokenVerifier;
use App\Services\TenantService;
use App\Support\ApiResponse;
use App\Support\SanctumMobileToken;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\UniqueConstraintViolationException;
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

            ['user' => $user, 'is_new' => $isNewUser] = $this->findOrCreateSocialUser(
                provider: $provider,
                providerId: (string) $socialUser->getId(),
                email: $socialUser->getEmail(),
                name: $socialUser->getName(),
                avatar: $socialUser->getAvatar(),
            );

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

    /**
     * Sign in with Apple (native iOS identity token).
     *
     * Accepts the relay address Apple issues when the user hides their email.
     */
    #[Group('OAuth (Advanced)', 'Optional browser-based OAuth for mobile/web clients. Email/password login is the primary method.', weight: 90)]
    #[Endpoint(
        title: 'Sign in with Apple',
        description: 'Verifies a native Sign in with Apple identity token and returns a Bearer token or a 2FA challenge. The token audience is the iOS bundle id.',
    )]
    public function loginWithApple(AppleLoginRequest $request, AppleIdentityTokenVerifier $tokens): JsonResponse
    {
        try {
            $identity = $tokens->verify(
                $request->string('identity_token')->toString(),
                $request->string('nonce')->toString(),
            );
        } catch (InvalidAppleIdentityTokenException $exception) {
            Log::warning('Apple sign-in rejected', ['reason' => $exception->getMessage()]);

            return ApiResponse::error('Unable to authenticate with Apple.', 401);
        }

        $portal = $request->filled('portal') ? $request->string('portal')->toString() : null;
        $existing = User::query()
            ->where('provider', 'apple')
            ->where('provider_id', $identity->subject)
            ->first();

        if (! $existing && $identity->email) {
            $existing = User::query()->where('email', $identity->email)->first();
        }

        if ($existing && $portal && ! $this->userMatchesPortal($existing, $portal)) {
            return ApiResponse::error('This account does not have access to this portal.', 403);
        }

        if (! $existing && in_array($portal, ['admin', 'oficina'], true)) {
            return ApiResponse::error('This account does not have access to this portal.', 403);
        }

        if (! $existing && ! $identity->email) {
            return ApiResponse::error('Apple did not share an email address.', 422);
        }

        try {
            ['user' => $user, 'is_new' => $isNewUser] = $this->findOrCreateSocialUser(
                provider: 'apple',
                providerId: $identity->subject,
                email: $identity->email,
                name: $request->filled('name') ? $request->string('name')->toString() : null,
                avatar: null,
                userType: $portal === 'lojista' ? 'garage' : null,
            );
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::error('Unable to authenticate with Apple.', 409);
        }

        if ($identity->emailVerified && $user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->save();
        }

        if ($isNewUser) {
            UserRegistered::dispatch($user, RegistrationSource::Oauth);
        }

        if ($twoFactorResponse = $this->maybeIssueTwoFactorChallenge($user)) {
            return $twoFactorResponse;
        }

        return SanctumMobileToken::loginResponse($user);
    }

    /**
     * @return array{user: User, is_new: bool}
     */
    private function findOrCreateSocialUser(
        string $provider,
        string $providerId,
        ?string $email,
        ?string $name,
        ?string $avatar,
        ?string $userType = null,
    ): array {
        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user) {
            if ($avatar && $user->avatar !== $avatar) {
                $user->update(['avatar' => $avatar]);
            }

            return ['user' => $user, 'is_new' => false];
        }

        if (filled($email)) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            $user->update([
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $avatar,
            ]);

            return ['user' => $user, 'is_new' => false];
        }

        if (! filled($email)) {
            throw new \InvalidArgumentException('OAuth provider did not return an email.');
        }

        $user = DB::transaction(function () use ($email, $name, $provider, $providerId, $avatar, $userType): User {
            $attributes = [
                'name' => filled($name) ? $name : 'Usuário',
                'email' => $email,
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $avatar,
                'password' => Hash::make(uniqid()),
            ];

            if ($userType !== null) {
                $attributes['user_type'] = $userType;
            }

            $user = User::create($attributes);
            (new TenantService)->createForUser($user);

            return $user->refresh();
        });

        return ['user' => $user, 'is_new' => true];
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
