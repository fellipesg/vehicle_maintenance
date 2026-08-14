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
}
