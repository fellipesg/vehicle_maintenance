<?php

namespace Database\Factories;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceWarranty;
use App\Models\WarrantyTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceWarranty>
 */
class MaintenanceWarrantyFactory extends Factory
{
    protected $model = MaintenanceWarranty::class;

    public function configure(): static
    {
        return $this->afterMaking(function (MaintenanceWarranty $warranty): void {
            if ($warranty->maintenance_id === null || $warranty->duration_days === null) {
                return;
            }

            $maintenance = Maintenance::find($warranty->maintenance_id);
            if ($maintenance === null) {
                return;
            }

            $dates = MaintenanceWarranty::computeDates($maintenance, (int) $warranty->duration_days);
            $warranty->starts_at = $dates['starts_at'];
            $warranty->ends_at = $dates['ends_at'];
        });
    }

    public function definition(): array
    {
        return [
            'maintenance_id' => Maintenance::factory(),
            'maintenance_item_id' => null,
            'warranty_template_id' => null,
            'scope' => WarrantyScope::Order,
            'name' => 'Garantia '.fake()->words(2, true),
            'body' => fake()->paragraph(),
            'duration_days' => fake()->numberBetween(30, 365),
            'starts_at' => now(),
            'ends_at' => now()->addDays(90),
        ];
    }

    public function forMaintenance(Maintenance $maintenance): static
    {
        return $this->state(fn () => [
            'maintenance_id' => $maintenance->id,
        ]);
    }

    public function fromTemplate(WarrantyTemplate $template, Maintenance $maintenance, ?MaintenanceItem $item = null): static
    {
        return $this->state(fn () => [
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item?->id,
            'warranty_template_id' => $template->id,
            'scope' => $template->scope,
            'name' => $template->name,
            'body' => $template->body,
            'duration_days' => $template->duration_days,
        ]);
    }

    public function orderScope(): static
    {
        return $this->state(fn () => [
            'scope' => WarrantyScope::Order,
            'maintenance_item_id' => null,
        ]);
    }

    public function itemScope(?MaintenanceItem $item = null): static
    {
        return $this->state(function () use ($item) {
            $maintenanceItem = $item ?? MaintenanceItem::factory()->create();

            return [
                'scope' => WarrantyScope::Item,
                'maintenance_id' => $maintenanceItem->maintenance_id,
                'maintenance_item_id' => $maintenanceItem->id,
            ];
        });
    }

    public function vigente(): static
    {
        return $this->state(fn () => [
            'maintenance_id' => Maintenance::factory()->create([
                'maintenance_date' => now()->subDays(10),
            ])->id,
            'duration_days' => 90,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'maintenance_id' => Maintenance::factory()->create([
                'maintenance_date' => now()->subDays(400),
            ])->id,
            'duration_days' => 30,
        ]);
    }
}
