<?php

namespace Database\Factories;

use App\Enums\WarrantyScope;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarrantyTemplate>
 */
class WarrantyTemplateFactory extends Factory
{
    protected $model = WarrantyTemplate::class;

    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'name' => 'Garantia '.fake()->words(2, true),
            'body' => fake()->paragraphs(2, true),
            'duration_days' => fake()->numberBetween(30, 365),
            'scope' => WarrantyScope::Order,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (WarrantyTemplate $template): void {
            if ($template->workshop_id && ! $template->tenant_id) {
                $workshop = Workshop::find($template->workshop_id);
                if ($workshop?->tenant_id) {
                    $template->tenant_id = $workshop->tenant_id;
                }
            }
        });
    }

    public function forWorkshop(Workshop $workshop): static
    {
        return $this->state(fn () => [
            'workshop_id' => $workshop->id,
            'tenant_id' => $workshop->tenant_id,
        ]);
    }

    public function orderScope(): static
    {
        return $this->state(fn () => [
            'scope' => WarrantyScope::Order,
        ]);
    }

    public function itemScope(): static
    {
        return $this->state(fn () => [
            'scope' => WarrantyScope::Item,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
