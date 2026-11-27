<?php

namespace Database\Factories;

use App\Enums\VisibilityEventAction;
use App\Models\InspectionVisibilityEvent;
use App\Models\User;
use App\Models\VehicleInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InspectionVisibilityEvent>
 */
class InspectionVisibilityEventFactory extends Factory
{
    protected $model = InspectionVisibilityEvent::class;

    public function definition(): array
    {
        return [
            'vehicle_inspection_id' => VehicleInspection::factory(),
            'action' => VisibilityEventAction::Hidden,
            'actor_user_id' => User::factory()->asAdmin(),
            'reason' => 'Laudo com chassi divergente.',
            'occurred_at' => now(),
        ];
    }

    public function hidden(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'action' => VisibilityEventAction::Hidden,
            'reason' => $reason ?? 'Laudo com chassi divergente.',
        ]);
    }

    /**
     * A devolução hoje não carrega motivo: a tela do admin só pede justificativa para suprimir.
     */
    public function restored(): static
    {
        return $this->state(fn (): array => [
            'action' => VisibilityEventAction::Restored,
            'reason' => null,
        ]);
    }
}
