<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DiagnoseInvoiceStorage extends Command
{
    protected $signature = 'invoices:diagnose-storage {--limit=3}';

    protected $description = 'Checks whether invoice files can be read from the configured storage disk';

    public function handle(): int
    {
        $disk = AppStorage::diskName();
        $config = config('filesystems.disks.'.$disk);

        $this->line('disk: '.$disk);
        $this->line('driver: '.($config['driver'] ?? '?'));
        $this->line('bucket: '.($config['bucket'] ?? '(empty)'));
        $this->line('region: '.($config['region'] ?? '(empty)'));
        $this->line('endpoint: '.($config['endpoint'] ?: '(default aws)'));
        $this->line('url: '.($config['url'] ?: '(none)'));
        $this->line('path_style: '.var_export($config['use_path_style_endpoint'] ?? null, true));
        $this->line('key set: '.(($config['key'] ?? '') !== '' ? 'yes' : 'NO'));
        $this->line('secret set: '.(($config['secret'] ?? '') !== '' ? 'yes' : 'NO'));

        $this->newLine();
        $this->line('network:');
        $storageHost = parse_url((string) ($config['endpoint'] ?? ''), PHP_URL_HOST);
        if (is_string($storageHost) && $storageHost !== '') {
            $this->probeDns($storageHost);
            $this->probeTcp($storageHost);
        }
        // Control hosts: if these connect and the storage host does not, egress
        // is fine and the storage endpoint itself is unreachable from here.
        $this->probeTcp('s3.us-east-2.amazonaws.com');
        $this->probeTcp('api.github.com');

        $invoices = Invoice::query()
            ->whereNotNull('file_path')
            ->latest('id')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'file_name', 'file_path']);

        if ($invoices->isEmpty()) {
            $this->warn('no invoices found');

            return self::SUCCESS;
        }

        $throwing = Storage::build(array_merge($config, ['throw' => true, 'report' => true]));

        foreach ($invoices as $invoice) {
            $path = (string) $invoice->file_path;
            $this->newLine();
            $this->line("invoice #{$invoice->id} {$path}");

            try {
                $this->line('  exists: '.($throwing->exists($path) ? 'yes' : 'no'));
                $this->line('  size: '.$throwing->size($path));
            } catch (\Throwable $e) {
                $this->error('  metadata failed: '.$e::class.': '.$e->getMessage());
            }

            try {
                $bytes = $throwing->get($path);
                $this->info('  get(): '.strlen((string) $bytes).' bytes');
            } catch (\Throwable $e) {
                $this->error('  get() failed: '.$e::class.': '.$e->getMessage());
            }

            try {
                $url = AppStorage::url($path, now()->addMinutes(10));
                $this->line('  signed host: '.parse_url($url, PHP_URL_HOST));
                $response = Http::timeout(30)->get($url);
                $this->line('  http status: '.$response->status().' bytes: '.strlen($response->body()));
            } catch (\Throwable $e) {
                $this->error('  http failed: '.$e::class.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function probeDns(string $host): void
    {
        $ip = gethostbyname($host);

        if ($ip === $host) {
            $this->error("  dns {$host}: FAILED to resolve");

            return;
        }

        $this->line("  dns {$host}: {$ip}");
    }

    private function probeTcp(string $host, int $port = 443): void
    {
        $errno = 0;
        $error = '';
        $start = microtime(true);
        $socket = @fsockopen('tcp://'.$host, $port, $errno, $error, 10);
        $ms = (int) round((microtime(true) - $start) * 1000);

        if ($socket === false) {
            $this->error("  tcp {$host}:{$port} FAILED after {$ms}ms [{$errno}] {$error}");

            return;
        }

        fclose($socket);
        $this->info("  tcp {$host}:{$port} ok in {$ms}ms");
    }
}
