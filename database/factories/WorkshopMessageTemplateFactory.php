<?php

namespace Database\Factories;

use App\Enums\WorkshopMessageTrigger;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopMessageTemplate>
 */
class WorkshopMessageTemplateFactory extends Factory
{
    protected $model = WorkshopMessageTemplate::class;

    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'trigger' => WorkshopMessageTrigger::ScheduledRevision,
            'service_category' => null,
            'title' => 'Revisão programada — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, seu {{vehicle}} está próximo de {{next_due_km}} km. Visite {{workshop_name}}.',
            'lead_kilometers' => 2_000,
            'min_days_since_service' => 60,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (WorkshopMessageTemplate $template): void {
            if ($template->workshop_id && ! $template->tenant_id) {
                $workshop = Workshop::find($template->workshop_id);
                if ($workshop?->tenant_id) {
                    $template->tenant_id = $workshop->tenant_id;
                }
            }
        });
    }

    public function scheduledRevision(): static
    {
        return $this->state(fn () => [
            'trigger' => WorkshopMessageTrigger::ScheduledRevision,
            'title' => 'Revisão — {{vehicle}}',
            'body' => 'Seu {{vehicle}} está próximo de {{next_due_km}} km (estimado {{estimated_km}} km).',
        ]);
    }

    public function correctiveFollowUp(): static
    {
        return $this->state(fn () => [
            'trigger' => WorkshopMessageTrigger::CorrectiveFollowUp,
            'service_category' => 'mechanical',
            'title' => 'Como está o serviço? — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, há {{days_since_service}} dias do serviço em {{last_service}} na {{workshop_name}}.',
        ]);
    }

    public function forWorkshop(Workshop $workshop): static
    {
        return $this->state(fn () => [
            'workshop_id' => $workshop->id,
            'tenant_id' => $workshop->tenant_id,
        ]);
    }
}
