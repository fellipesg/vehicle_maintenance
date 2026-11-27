<?php

namespace Database\Factories;

use App\Enums\VisibilityEventAction;
use App\Models\Maintenance;
use App\Models\MaintenanceVisibilityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceVisibilityEvent>
 */
class MaintenanceVisibilityEventFactory extends Factory
{
    protected $model = MaintenanceVisibilityEvent::class;

    public function definition(): array
    {
        return [
            'maintenance_id' => Maintenance::factory(),
            'action' => VisibilityEventAction::Hidden,
            'actor_user_id' => User::factory()->asAdmin(),
            'reason' => 'Fraude confirmada.',
            'occurred_at' => now(),
        ];
    }

    public function hidden(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'action' => VisibilityEventAction::Hidden,
            'reason' => $reason ?? 'Fraude confirmada.',
        ]);
    }

    /**
     * A devolução hoje não carrega motivo: a tela do admin só pede justificativa para ocultar.
     */
    public function restored(): static
    {
        return $this->state(fn (): array => [
            'action' => VisibilityEventAction::Restored,
            'reason' => null,
        ]);
    }
}
