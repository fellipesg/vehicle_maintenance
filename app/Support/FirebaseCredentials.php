<?php

namespace App\Support;

class FirebaseCredentials
{
    /**
     * @return array<string, mixed>|non-empty-string
     */
    public static function resolve(): array|string
    {
        $base64 = config('firebase.credentials_base64');
        if (is_string($base64) && $base64 !== '') {
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                throw new \RuntimeException('FIREBASE_CREDENTIALS_BASE64 is not valid base64.');
            }

            return self::decodeJson($decoded, 'FIREBASE_CREDENTIALS_BASE64');
        }

        $json = config('firebase.credentials_json');
        if (is_string($json) && $json !== '') {
            return self::decodeJson($json, 'FIREBASE_CREDENTIALS_JSON');
        }

        $credentialsPath = config('firebase.credentials_path');
        if (is_string($credentialsPath) && $credentialsPath !== '') {
            $fullPath = storage_path('app/'.$credentialsPath);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        throw new \RuntimeException(
            'Firebase credentials not found. Set FIREBASE_CREDENTIALS_BASE64 or FIREBASE_CREDENTIALS_JSON on Laravel Cloud, '.
            'or place the service account JSON at storage/app/'.($credentialsPath ?: 'firebase-service-account.json').'.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(string $json, string $source): array
    {
        $credentials = json_decode($json, true);

        if (! is_array($credentials)) {
            throw new \RuntimeException("{$source} does not contain valid JSON.");
        }

        return $credentials;
    }
}
