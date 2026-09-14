<?php

namespace Tests\Feature;

use App\Enums\WorkshopMessageTrigger;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopMessageTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_user_can_create_message_template(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $workshop = Workshop::factory()->forUser($user)->create();

        $this->postJson("/api/v1/workshops/{$workshop->id}/message-templates", [
            'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
            'title' => 'Revisão — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, visite {{workshop_name}}.',
            'lead_kilometers' => 1500,
        ])->assertCreated()
            ->assertJsonPath('data.trigger', WorkshopMessageTrigger::ScheduledRevision->value)
            ->assertJsonPath('data.lead_kilometers', 1500);

        $this->assertDatabaseHas('workshop_message_templates', [
            'workshop_id' => $workshop->id,
            'tenant_id' => $user->tenant_id,
            'title' => 'Revisão — {{vehicle}}',
        ]);
    }

    public function test_workshop_user_can_list_own_templates(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $workshop = Workshop::factory()->forUser($user)->create();
        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->count(2)->create();

        $this->getJson("/api/v1/workshops/{$workshop->id}/message-templates")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_workshop_user_cannot_manage_other_workshop_templates(): void
    {
        $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $otherWorkshop = Workshop::factory()->create();

        $this->postJson("/api/v1/workshops/{$otherWorkshop->id}/message-templates", [
            'trigger' => WorkshopMessageTrigger::CorrectiveFollowUp->value,
            'title' => 'Follow-up',
            'body' => 'Corpo',
        ])->assertForbidden();
    }

    public function test_regular_user_cannot_create_message_template(): void
    {
        $this->actingAsApiUser();
        $workshop = Workshop::factory()->create();

        $this->postJson("/api/v1/workshops/{$workshop->id}/message-templates", [
            'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
            'title' => 'Revisão',
            'body' => 'Corpo',
        ])->assertForbidden();
    }

    public function test_workshop_user_can_update_and_delete_template(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $workshop = Workshop::factory()->forUser($user)->create();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->create([
            'title' => 'Antigo',
        ]);

        $this->putJson("/api/v1/workshops/{$workshop->id}/message-templates/{$template->id}", [
            'title' => 'Novo',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Novo')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/workshops/{$workshop->id}/message-templates/{$template->id}")
            ->assertOk();

        $this->assertDatabaseMissing('workshop_message_templates', ['id' => $template->id]);
    }
}
