<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Some platforms resolve the storage hostname to a private address that has no
 * route to the bucket, so every request hangs until it times out. When that is
 * detected, connections are pinned to the addresses from public DNS (the
 * equivalent of `curl --resolve`), which keeps the hostname for TLS and signing.
 *
 * IPs are memoized in-process only. Never touch the database cache here: upload
 * and report flows often run inside a Postgres transaction, and a failed cache
 * query aborts that transaction even when PHP catches the exception (SQLSTATE
 * 25P02).
 */
class StorageEndpointResolver
{
    /** @var array<string, string|null> */
    private static array $memo = [];

    /** @var array<string, list<string>> */
    private static array $ipMemo = [];

    /**
     * Adds pinned resolution to an s3 disk config when needed.
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
     * A single `host:port:ip1,ip2` entry, or null when pinning is not needed.
     */
    public static function resolveEntry(mixed $endpoint): ?string
    {
        $mode = config('filesystems.pin_public_dns', 'auto');

        if ($mode === false || $mode === 'false' || ! is_string($endpoint) || $endpoint === '') {
            return null;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($endpoint, PHP_URL_PORT) ?: 443;
        $forced = $mode === true || $mode === 'true';

        $memoKey = $host.':'.$port.':'.var_export($mode, true);

        if (array_key_exists($memoKey, self::$memo)) {
            return self::$memo[$memoKey];
        }

        if (! $forced && ! self::resolvesToPrivateAddress($host)) {
            return self::$memo[$memoKey] = null;
        }

        $ips = self::publicIps($host);

        return self::$memo[$memoKey] = $ips === []
            ? null
            : $host.':'.$port.':'.implode(',', $ips);
    }

    /**
     * True when the platform's own resolver points the host at an address that
     * cannot reach the public internet, which is what breaks the connection.
     */
    public static function resolvesToPrivateAddress(string $host): bool
    {
        $ip = gethostbyname($host);

        if ($ip === $host || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * @return list<string>
     */
    public static function publicIps(string $host): array
    {
        $configured = config('filesystems.public_ips');

        if (is_string($configured) && $configured !== '') {
            return self::onlyValidIps(explode(',', $configured));
        }

        if (isset(self::$ipMemo[$host])) {
            return self::$ipMemo[$host];
        }

        return self::$ipMemo[$host] = self::lookup($host);
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
