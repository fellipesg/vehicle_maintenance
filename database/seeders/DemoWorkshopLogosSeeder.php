<?php

namespace Database\Seeders;

use App\Models\Workshop;
use App\Support\AppStorage;
use App\Support\DemoWorkshopLogoGenerator;
use Database\Seeders\Concerns\ResolvesDemoWorkshops;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class DemoWorkshopLogosSeeder extends Seeder
{
    use ResolvesDemoWorkshops;

    /**
     * @return list<array{name: string, email: ?string, slug: string, label: string, rgb: array{0: int, 1: int, 2: int}}>
     */
    public static function workshops(): array
    {
        return [
            [
                'name' => DemoWorkshopAccountsSeeder::DIVESA_NAME,
                'email' => DemoWorkshopAccountsSeeder::DIVESA_EMAIL,
                'slug' => 'divesa',
                'label' => 'DIVESA Londrina',
                'rgb' => [0, 51, 153],
            ],
            [
                'name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME,
                'email' => DemoWorkshopAccountsSeeder::BROTHERS_EMAIL,
                'slug' => 'brothers',
                'label' => 'Brothers Auto',
                'rgb' => [180, 30, 30],
            ],
            [
                'name' => DemoWorkshopAccountsSeeder::DEV_WORKSHOP_NAME,
                'email' => DevPortalUsersSeeder::WORKSHOP_EMAIL,
                'slug' => 'dev-oficina',
                'label' => 'Dev Oficina',
                'rgb' => [34, 139, 34],
            ],
        ];
    }

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DemoWorkshopLogosSeeder skipped: only runs in local/testing environments.');

            return;
        }

        $seeded = 0;

        foreach (self::workshops() as $config) {
            $workshop = $this->resolveWorkshopForLogo($config['name'], $config['email']);

            if ($workshop === null) {
                $this->command?->warn("DemoWorkshopLogosSeeder skipped missing workshop: {$config['name']}");

                continue;
            }

            $logoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_'.$config['slug'].'.jpg';

            if (! AppStorage::isWorkshopLogoPath($logoPath) || ! AppStorage::usesCoversDisk($logoPath)) {
                throw new \RuntimeException("Invalid demo logo path: {$logoPath}");
            }

            $jpeg = $this->fetchLogoJpeg($config['slug'], $config['label'], $config['rgb']);
            AppStorage::putPublic($logoPath, $jpeg);

            if ($workshop->logo_path !== null && $workshop->logo_path !== $logoPath) {
                $oldPath = $workshop->logo_path;
                if (AppStorage::coversDisk()->exists($oldPath)) {
                    AppStorage::coversDisk()->delete($oldPath);
                }
            }

            $workshop->update(['logo_path' => $logoPath]);
            $seeded++;

            $this->command?->line("  Logo: {$workshop->name} → {$logoPath}");
        }

        $this->command?->info("Demo workshop logos ready ({$seeded} oficina(s)).");
    }

    private function resolveWorkshopForLogo(string $officialName, ?string $email): ?Workshop
    {
        $byName = Workshop::query()
            ->where('name', $officialName)
            ->orderBy('id')
            ->first();

        if ($byName !== null) {
            return $byName;
        }

        return $this->resolveDemoWorkshop($officialName, $email);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function fetchLogoJpeg(string $slug, string $label, array $rgb): string
    {
        $url = sprintf(
            'https://picsum.photos/seed/%s/%d/%d',
            $slug,
            DemoWorkshopLogoGenerator::WIDTH,
            DemoWorkshopLogoGenerator::HEIGHT,
        );

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->retry(2, 100)
                ->throw()
                ->get($url);

            $jpeg = $response->body();

            if ($jpeg === '' || ! str_starts_with($jpeg, "\xFF\xD8\xFF")) {
                throw new \RuntimeException('Invalid JPEG response from Picsum.');
            }

            return $jpeg;
        } catch (\Throwable) {
            return DemoWorkshopLogoGenerator::jpeg($label, $rgb);
        }
    }
}
