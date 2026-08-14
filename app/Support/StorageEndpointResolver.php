<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Laravel Cloud resolves `*.neon.tech` to a private address that has no route to
 * Object Storage, so the SDK never connects. Pinning the connection to the
 * public addresses (the equivalent of `curl --resolve`) keeps the hostname for
 * TLS and signing while bypassing the internal resolver.
 */
class StorageEndpointResolver
{
    private const CACHE_KEY = 'storage:public-ips:';

    private const CACHE_TTL_MINUTES = 360;

    /**
     * Adds pinned resolution to an s3 disk config when enabled.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function apply(array $config): array
    {
        $entry = self::resolveEntry($config['endpoint'] ?? null);

        if ($entry === null) {
            return $config;
        }

        $config['http'] = array_merge($config['http'] ?? [], [
            'curl' => array_replace(
                $config['http']['curl'] ?? [],
                [CURLOPT_RESOLVE => [$entry]]
            ),
        ]);

        return $config;
    }

    /**
     * Guzzle/cURL options that pin the storage endpoint, for plain HTTP calls.
     *
     * @return array<string, mixed>
     */
    public static function httpOptions(): array
    {
        $entry = self::resolveEntry(config('filesystems.disks.'.AppStorage::diskName().'.endpoint'));

        return $entry === null ? [] : ['curl' => [CURLOPT_RESOLVE => [$entry]]];
    }

    /**
     * A single `host:port:ip1,ip2` entry, or null when pinning is off.
     */
    private static function resolveEntry(mixed $endpoint): ?string
    {
        if (! config('filesystems.pin_public_dns', false) || ! is_string($endpoint) || $endpoint === '') {
            return null;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($endpoint, PHP_URL_PORT) ?: 443;
        $ips = self::publicIps($host);

        return $ips === [] ? null : $host.':'.$port.':'.implode(',', $ips);
    }

    /**
     * @return list<string>
     */
    private static function publicIps(string $host): array
    {
        $configured = config('filesystems.public_ips');

        if (is_string($configured) && $configured !== '') {
            return self::onlyValidIps(explode(',', $configured));
        }

        try {
            return Cache::remember(
                self::CACHE_KEY.$host,
                now()->addMinutes(self::CACHE_TTL_MINUTES),
                fn () => self::lookup($host)
            );
        } catch (\Throwable $e) {
            report($e);

            return self::lookup($host);
        }
    }

    /**
     * @return list<string>
     */
    private static function lookup(string $host): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get('https://cloudflare-dns.com/dns-query', ['name' => $host, 'type' => 'A']);

            if (! $response->successful()) {
                return [];
            }

            return self::onlyValidIps(
                collect($response->json('Answer') ?? [])
                    ->where('type', 1)
                    ->pluck('data')
                    ->all()
            );
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $ips
     * @return list<string>
     */
    private static function onlyValidIps(array $ips): array
    {
        return collect($ips)
            ->map(fn ($ip) => is_string($ip) ? trim($ip) : '')
            ->filter(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
