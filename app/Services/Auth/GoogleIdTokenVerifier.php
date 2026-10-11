<?php

namespace App\Services\Auth;

use App\Exceptions\InvalidGoogleIdTokenException;
use App\Support\Jwt\RsaJwk;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Confere o ID token que o login nativo do Google (google_sign_in no app) entrega: assinatura RS256
 * pelas chaves públicas do Google, emissor, audiência (os clientes OAuth do RevisaLog), validade e
 * e-mail verificado. Mesmo modelo do AppleIdentityTokenVerifier.
 */
class GoogleIdTokenVerifier
{
    private const CACHE_KEY = 'google.id_token.jwks';

    private const KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /** @var list<string> */
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function verify(string $idToken): GoogleIdentity
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new InvalidGoogleIdTokenException('Malformed Google ID token.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $parts;
        $header = $this->jsonSegment($headerSegment);
        $payload = $this->jsonSegment($payloadSegment);

        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null) || $header['kid'] === '') {
            throw new InvalidGoogleIdTokenException('Unsupported Google ID token header.');
        }

        $key = $this->matchingKey($header['kid'], refresh: false);
        if (! is_string($key['n'] ?? null) || ! is_string($key['e'] ?? null)) {
            throw new InvalidGoogleIdTokenException('Google public key is incomplete.');
        }

        $verified = openssl_verify(
            $headerSegment.'.'.$payloadSegment,
            $this->base64UrlDecode($signatureSegment),
            RsaJwk::toPem($this->base64UrlDecode($key['n']), $this->base64UrlDecode($key['e'])),
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            throw new InvalidGoogleIdTokenException('Google ID token signature is invalid.');
        }

        $this->assertClaims($payload);

        return new GoogleIdentity(
            subject: $payload['sub'],
            email: mb_strtolower($payload['email']),
            name: is_string($payload['name'] ?? null) && $payload['name'] !== '' ? $payload['name'] : null,
            avatar: is_string($payload['picture'] ?? null) && $payload['picture'] !== '' ? $payload['picture'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertClaims(array $payload): void
    {
        if (! in_array($payload['iss'] ?? null, self::ISSUERS, true)) {
            throw new InvalidGoogleIdTokenException('Google ID token issuer is invalid.');
        }

        $audiences = array_values(array_filter((array) config('services.google.token_audiences')));
        if ($audiences === [] || ! in_array($payload['aud'] ?? null, $audiences, true)) {
            throw new InvalidGoogleIdTokenException('Google ID token audience is invalid.');
        }

        $expiresAt = $payload['exp'] ?? null;
        if (! is_numeric($expiresAt) || (int) $expiresAt < now()->getTimestamp() - 60) {
            throw new InvalidGoogleIdTokenException('Google ID token is expired.');
        }

        if (! is_string($payload['sub'] ?? null) || $payload['sub'] === '') {
            throw new InvalidGoogleIdTokenException('Google ID token subject is missing.');
        }

        $email = $payload['email'] ?? null;
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidGoogleIdTokenException('Google ID token has no valid email.');
        }

        if (! filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new InvalidGoogleIdTokenException('Google email is not verified.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function matchingKey(string $kid, bool $refresh): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        /** @var list<array<string, mixed>> $keys */
        $keys = Cache::remember(self::CACHE_KEY, now()->addHours(6), fn (): array => $this->fetchKeys());

        foreach ($keys as $key) {
            if (($key['kid'] ?? null) === $kid && ($key['kty'] ?? null) === 'RSA') {
                return $key;
            }
        }

        if (! $refresh) {
            return $this->matchingKey($kid, refresh: true);
        }

        throw new InvalidGoogleIdTokenException('Google public key was not found.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchKeys(): array
    {
        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException, throw: false)
            ->get(self::KEYS_URL);

        $keys = $response->successful() ? $response->json('keys') : null;
        if (! is_array($keys)) {
            throw new InvalidGoogleIdTokenException('Unable to fetch Google public keys.');
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSegment(string $segment): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);
        if (! is_array($decoded)) {
            throw new InvalidGoogleIdTokenException('Malformed Google ID token.');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = RsaJwk::base64UrlDecode($value);
        if ($decoded === null) {
            throw new InvalidGoogleIdTokenException('Malformed Google ID token.');
        }

        return $decoded;
    }
}
