<?php

namespace App\Services\Auth;

use App\Exceptions\InvalidAppleIdentityTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppleIdentityTokenVerifier
{
    private const CACHE_KEY = 'apple.identity.jwks';

    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    private const ISSUER = 'https://appleid.apple.com';

    public function verify(string $identityToken, string $rawNonce): AppleIdentity
    {
        $parts = explode('.', $identityToken);
        if (count($parts) !== 3) {
            throw new InvalidAppleIdentityTokenException('Malformed Apple identity token.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $parts;
        $header = $this->jsonSegment($headerSegment);
        $payload = $this->jsonSegment($payloadSegment);

        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null) || $header['kid'] === '') {
            throw new InvalidAppleIdentityTokenException('Unsupported Apple identity token header.');
        }

        $signed = $headerSegment.'.'.$payloadSegment;
        $signature = $this->base64UrlDecode($signatureSegment);
        $pem = $this->publicKeyPem($header['kid']);
        $verified = openssl_verify($signed, $signature, $pem, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new InvalidAppleIdentityTokenException('Apple identity token signature is invalid.');
        }

        $this->assertClaims($payload, $rawNonce);

        $email = is_string($payload['email'] ?? null) && $payload['email'] !== ''
            ? $payload['email']
            : null;
        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email !== null && ! $emailVerified) {
            throw new InvalidAppleIdentityTokenException('Apple email is not verified.');
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidAppleIdentityTokenException('Apple email is invalid.');
        }

        return new AppleIdentity(
            subject: $payload['sub'],
            email: $email,
            emailVerified: $emailVerified,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertClaims(array $payload, string $rawNonce): void
    {
        if (($payload['iss'] ?? null) !== self::ISSUER) {
            throw new InvalidAppleIdentityTokenException('Apple identity token issuer is invalid.');
        }

        $audience = $payload['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $clientId = (string) config('services.apple.client_id');

        if ($clientId === '' || ! in_array($clientId, $audiences, true)) {
            throw new InvalidAppleIdentityTokenException('Apple identity token audience is invalid.');
        }

        $expiresAt = $payload['exp'] ?? null;
        if (! is_numeric($expiresAt) || (int) $expiresAt < now()->getTimestamp() - 60) {
            throw new InvalidAppleIdentityTokenException('Apple identity token is expired.');
        }

        if (! is_string($payload['sub'] ?? null) || $payload['sub'] === '') {
            throw new InvalidAppleIdentityTokenException('Apple identity token subject is missing.');
        }

        $tokenNonce = $payload['nonce'] ?? null;
        $expectedNonce = hash('sha256', $rawNonce);

        if (! is_string($tokenNonce) || ! hash_equals($expectedNonce, $tokenNonce)) {
            throw new InvalidAppleIdentityTokenException('Apple identity token nonce does not match.');
        }
    }

    private function publicKeyPem(string $kid): string
    {
        $key = $this->matchingKey($kid, refresh: false);
        $modulus = $key['n'] ?? null;
        $exponent = $key['e'] ?? null;

        if (! is_string($modulus) || ! is_string($exponent)) {
            throw new InvalidAppleIdentityTokenException('Apple public key is incomplete.');
        }

        return $this->jwkToPem($modulus, $exponent);
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
        $keys = Cache::remember(self::CACHE_KEY, now()->addHours(12), fn (): array => $this->fetchKeys());

        foreach ($keys as $key) {
            if (($key['kid'] ?? null) === $kid && ($key['kty'] ?? null) === 'RSA') {
                return $key;
            }
        }

        if (! $refresh) {
            return $this->matchingKey($kid, refresh: true);
        }

        throw new InvalidAppleIdentityTokenException('Apple public key was not found.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchKeys(): array
    {
        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->retry(2, 200, function ($exception): bool {
                return $exception instanceof ConnectionException;
            }, throw: false)
            ->get(self::KEYS_URL);

        if (! $response->successful()) {
            throw new InvalidAppleIdentityTokenException('Unable to fetch Apple public keys.');
        }

        $keys = $response->json('keys');

        if (! is_array($keys)) {
            throw new InvalidAppleIdentityTokenException('Apple public keys response is invalid.');
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
            throw new InvalidAppleIdentityTokenException('Malformed Apple identity token.');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidAppleIdentityTokenException('Malformed Apple identity token.');
        }

        return $decoded;
    }

    private function jwkToPem(string $modulus, string $exponent): string
    {
        $modulus = ltrim($this->base64UrlDecode($modulus), "\x00");
        $exponent = ltrim($this->base64UrlDecode($exponent), "\x00");

        if ($modulus === '' || $exponent === '') {
            throw new InvalidAppleIdentityTokenException('Apple public key is incomplete.');
        }

        if ((ord($modulus[0]) & 0x80) !== 0) {
            $modulus = "\x00".$modulus;
        }

        if ((ord($exponent[0]) & 0x80) !== 0) {
            $exponent = "\x00".$exponent;
        }

        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($modulus).$this->asn1Integer($exponent),
        );
        $bitString = "\x03".$this->asn1Length(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithm === false) {
            throw new InvalidAppleIdentityTokenException('Apple public key is incomplete.');
        }
        $publicKey = $this->asn1Sequence($algorithm.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($publicKey), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $value): string
    {
        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($encoded)).$encoded;
    }
}
