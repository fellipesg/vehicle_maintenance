<?php

namespace Database\Seeders;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use App\Support\AppStorage;
use Database\Seeders\Concerns\ResolvesDemoWorkshops;
use Illuminate\Database\Seeder;

class DemoMaintenanceWarrantiesSeeder extends Seeder
{
    use ResolvesDemoWorkshops;

    public const PLATE = 'QOS6H54';

    public const ANCHOR_ORDER_DIVESA = 'DEMO-GARANTIA-OS-DIVESA';

    public const ANCHOR_ITEM_DIVESA = 'DEMO-GARANTIA-PECA-DIVESA';

    public const ANCHOR_ORDER_BROTHERS = 'DEMO-GARANTIA-OS-BROTHERS';

    public const ANCHOR_ITEM_BROTHERS = 'DEMO-GARANTIA-PECA-BROTHERS';

    public const ANCHOR_ORDER_DEV = 'DEMO-GARANTIA-OS-DEV';

    public const ANCHOR_ITEM_DEV = 'DEMO-GARANTIA-PECA-DEV';

    public const DIVESA_INVOICE_PATH = 'invoices/divesa_revisao_b_qos6h54.pdf';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DemoMaintenanceWarrantiesSeeder skipped: only runs in local/testing environments.');

            return;
        }

        $vehicle = Vehicle::query()->where('license_plate', self::PLATE)->first();

        if ($vehicle === null) {
            $this->command?->warn('DemoMaintenanceWarrantiesSeeder skipped: vehicle '.self::PLATE.' not found.');

            return;
        }

        $owner = User::query()->where('email', 'fgoncalves2008@gmail.com')->first();

        $this->pruneDuplicateDemoMaintenances($vehicle);
        $this->ensureVehicleOdometer($vehicle);

        $divesa = $this->resolveDemoWorkshop(DemoWorkshopAccountsSeeder::DIVESA_NAME, DemoWorkshopAccountsSeeder::DIVESA_EMAIL);
        $brothers = $this->resolveDemoWorkshop(DemoWorkshopAccountsSeeder::BROTHERS_NAME, DemoWorkshopAccountsSeeder::BROTHERS_EMAIL);
        $dev = $this->resolveDemoWorkshop(DemoWorkshopAccountsSeeder::DEV_WORKSHOP_NAME, DevPortalUsersSeeder::WORKSHOP_EMAIL);

        if ($divesa !== null) {
            $this->seedDivesaMaintenanceWarranties($vehicle, $divesa);
        } else {
            $this->command?->warn('DemoMaintenanceWarrantiesSeeder skipped DIVESA warranties: workshop not found.');
        }

        if ($brothers !== null && $owner !== null) {
            $this->seedBrothersDemoMaintenances($vehicle, $owner, $brothers);
        } else {
            $this->command?->warn('DemoMaintenanceWarrantiesSeeder skipped Brothers demo OS: workshop or owner not found.');
        }

        if ($dev !== null && $owner !== null) {
            $this->seedDevDemoMaintenance($vehicle, $owner, $dev);
        }

        if ($owner !== null) {
            $this->seedControlMaintenanceWithoutLogo($vehicle, $owner);
        }

        $this->pruneDuplicateDemoMaintenances($vehicle);
        $this->syncVehicleOdometerToMaintenances($vehicle);

        $this->command?->info('Demo maintenance warranties ready for '.self::PLATE.'.');
    }

    private function pruneDuplicateDemoMaintenances(Vehicle $vehicle): void
    {
        $canonicalTypes = [
            'Demo controle — sem logo/garantia',
            'Demo Brothers — pastilhas (vigente)',
            'Demo Brothers — alinhamento (expirada)',
            'Demo Dev — revisão rápida',
        ];

        foreach ($canonicalTypes as $maintenanceType) {
            $records = Maintenance::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('maintenance_type', $maintenanceType)
                ->orderBy('id')
                ->get();

            $records->slice(1)->each(function (Maintenance $maintenance): void {
                $maintenance->items()->delete();
                $maintenance->invoices()->each(fn ($invoice) => $invoice->delete());
                MaintenanceWarranty::query()->where('maintenance_id', $maintenance->id)->delete();
                $maintenance->delete();
            });
        }

        Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'like', 'Demo controle%')
            ->where('maintenance_type', '!=', 'Demo controle — sem logo/garantia')
            ->each(function (Maintenance $maintenance): void {
                $maintenance->items()->delete();
                $maintenance->invoices()->each(fn ($invoice) => $invoice->delete());
                MaintenanceWarranty::query()->where('maintenance_id', $maintenance->id)->delete();
                $maintenance->delete();
            });
    }

    private function ensureVehicleOdometer(Vehicle $vehicle): void
    {
        $vehicle->update([
            'odometer_at_registration' => $vehicle->odometer_at_registration ?? 50_000,
        ]);
    }

    private function syncVehicleOdometerToMaintenances(Vehicle $vehicle): void
    {
        $maxMaintenanceKm = (int) Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('kilometers')
            ->max('kilometers');

        if ($maxMaintenanceKm <= 0) {
            return;
        }

        $vehicle->update([
            'current_kilometers' => max(
                (int) ($vehicle->current_kilometers ?? 0),
                $maxMaintenanceKm,
            ),
        ]);
    }

    private function seedDivesaMaintenanceWarranties(Vehicle $vehicle, Workshop $workshop): void
    {
        $maintenance = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'Revisão B (Assyst B)')
            ->whereDate('maintenance_date', '2026-03-10')
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($maintenance === null) {
            $this->command?->warn('DemoMaintenanceWarrantiesSeeder skipped DIVESA OS: Revisão B (Assyst B) not found.');

            return;
        }

        $this->ensureDivesaInvoiceOnDisk($maintenance);

        $orderTemplate = $this->upsertTemplate(
            $workshop,
            self::ANCHOR_ORDER_DIVESA,
            WarrantyScope::Order,
            365,
            'Garantia geral da ordem de serviço DIVESA. Âncora: '.self::ANCHOR_ORDER_DIVESA,
        );

        $itemTemplate = $this->upsertTemplate(
            $workshop,
            self::ANCHOR_ITEM_DIVESA,
            WarrantyScope::Item,
            180,
            'Garantia da peça substituída na DIVESA. Âncora: '.self::ANCHOR_ITEM_DIVESA,
        );

        $this->upsertOrderWarranty($maintenance, $orderTemplate);

        $batteryItem = $maintenance->items()
            ->where('name', 'like', '%Bateria%')
            ->first();

        if ($batteryItem === null) {
            $this->command?->warn('DemoMaintenanceWarrantiesSeeder skipped DIVESA item warranty: battery item not found.');

            return;
        }

        $this->upsertItemWarranty($maintenance, $batteryItem, $itemTemplate);
    }

    private function seedBrothersDemoMaintenances(Vehicle $vehicle, User $owner, Workshop $workshop): void
    {
        $orderTemplate = $this->upsertTemplate(
            $workshop,
            self::ANCHOR_ORDER_BROTHERS,
            WarrantyScope::Order,
            365,
            'Garantia geral Brothers Auto Service. Âncora: '.self::ANCHOR_ORDER_BROTHERS,
        );

        $itemTemplate = $this->upsertTemplate(
            $workshop,
            self::ANCHOR_ITEM_BROTHERS,
            WarrantyScope::Item,
            180,
            'Garantia de peça Brothers. Âncora: '.self::ANCHOR_ITEM_BROTHERS,
        );

        $vigente = Maintenance::updateOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Demo Brothers — pastilhas (vigente)',
            ],
            [
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_id' => $workshop->id,
                'workshop_name' => $workshop->name,
                'maintenance_date' => '2026-02-08',
                'kilometers' => 96_000,
                'description' => 'Troca de pastilhas dianteiras com garantia vigente para demo PDF.',
                'service_category' => 'mechanical',
                'is_manufacturer_required' => false,
            ],
        );

        $this->upsertOrderWarranty($vigente, $orderTemplate);

        $brothersItem = MaintenanceItem::updateOrCreate(
            [
                'maintenance_id' => $vigente->id,
                'name' => 'Pastilhas dianteiras',
            ],
            [
                'quantity' => 1,
                'unit_price' => 420.00,
                'total_price' => 420.00,
            ],
        );

        $this->upsertItemWarranty($vigente, $brothersItem, $itemTemplate);

        $expired = Maintenance::updateOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Demo Brothers — alinhamento (expirada)',
            ],
            [
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_id' => $workshop->id,
                'workshop_name' => $workshop->name,
                'maintenance_date' => '2024-06-12',
                'kilometers' => 82_000,
                'description' => 'Alinhamento e balanceamento com garantia expirada para contraste no PDF.',
                'service_category' => 'mechanical',
                'is_manufacturer_required' => false,
            ],
        );

        $this->upsertOrderWarranty($expired, $orderTemplate);
    }

    private function seedDevDemoMaintenance(Vehicle $vehicle, User $owner, Workshop $workshop): void
    {
        $orderTemplate = $this->upsertTemplate(
            $workshop,
            self::ANCHOR_ORDER_DEV,
            WarrantyScope::Order,
            365,
            'Garantia geral Dev Oficina. Âncora: '.self::ANCHOR_ORDER_DEV,
        );

        $maintenance = Maintenance::updateOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Demo Dev — revisão rápida',
            ],
            [
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_id' => $workshop->id,
                'workshop_name' => $workshop->name,
                'maintenance_date' => '2026-01-28',
                'kilometers' => 95_500,
                'description' => 'Revisão rápida de demonstração com logo Dev Oficina.',
                'service_category' => 'mechanical',
                'is_manufacturer_required' => false,
            ],
        );

        $this->upsertOrderWarranty($maintenance, $orderTemplate);
    }

    private function seedControlMaintenanceWithoutLogo(Vehicle $vehicle, User $owner): void
    {
        Maintenance::updateOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Demo controle — sem logo/garantia',
            ],
            [
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_name' => 'Oficina Independente (sem logo)',
                'maintenance_date' => '2025-11-22',
                'kilometers' => 94_000,
                'description' => 'OS de controle visual: sem logo de oficina e sem garantias emitidas.',
                'service_category' => 'other',
                'is_manufacturer_required' => false,
            ],
        );
    }

    private function upsertTemplate(
        Workshop $workshop,
        string $name,
        WarrantyScope $scope,
        int $durationDays,
        string $body,
    ): WarrantyTemplate {
        return WarrantyTemplate::updateOrCreate(
            [
                'workshop_id' => $workshop->id,
                'name' => $name,
            ],
            [
                'tenant_id' => $workshop->tenant_id,
                'body' => $body,
                'duration_days' => $durationDays,
                'scope' => $scope,
                'is_active' => true,
            ],
        );
    }

    private function upsertOrderWarranty(Maintenance $maintenance, WarrantyTemplate $template): MaintenanceWarranty
    {
        $snapshot = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance);

        return MaintenanceWarranty::updateOrCreate(
            [
                'maintenance_id' => $maintenance->id,
                'scope' => WarrantyScope::Order,
            ],
            [
                'maintenance_item_id' => null,
                'warranty_template_id' => $template->id,
                'name' => $snapshot->name,
                'body' => $snapshot->body,
                'duration_days' => $snapshot->duration_days,
                'starts_at' => $snapshot->starts_at,
                'ends_at' => $snapshot->ends_at,
            ],
        );
    }

    private function upsertItemWarranty(
        Maintenance $maintenance,
        MaintenanceItem $item,
        WarrantyTemplate $template,
    ): MaintenanceWarranty {
        $snapshot = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance, $item);

        return MaintenanceWarranty::updateOrCreate(
            [
                'maintenance_item_id' => $item->id,
            ],
            [
                'maintenance_id' => $maintenance->id,
                'scope' => WarrantyScope::Item,
                'warranty_template_id' => $template->id,
                'name' => $snapshot->name,
                'body' => $snapshot->body,
                'duration_days' => $snapshot->duration_days,
                'starts_at' => $snapshot->starts_at,
                'ends_at' => $snapshot->ends_at,
            ],
        );
    }

    private function ensureDivesaInvoiceOnDisk(Maintenance $maintenance): void
    {
        $invoice = $maintenance->invoices()->where('invoice_type', 'general')->first();

        if ($invoice === null) {
            return;
        }

        $path = (string) $invoice->file_path;

        if ($path === '') {
            return;
        }

        if (AppStorage::disk()->exists($path)) {
            return;
        }

        AppStorage::disk()->put($path, self::minimalPdfStub());
    }

    public static function minimalPdfStub(): string
    {
        return "%PDF-1.4\n1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n2 0 obj<< /Type /Pages /Kids [] /Count 0 >>endobj\nxref\n0 3\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \ntrailer<< /Root 1 0 R /Size 3 >>\nstartxref\n120\n%%EOF";
    }
}
