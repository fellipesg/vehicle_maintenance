<?php

namespace Tests\Feature\Web;

use App\Enums\WorkshopStatus;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\WorkshopApprovalDecidedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fila de análise das oficinas em /admin/oficinas. A página continua sendo o diretório (busca,
 * mapa, selos): a análise entra como aba, com o contador de pendentes.
 */
class AdminWorkshopQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asAdmin()->create();
    }

    public function test_the_list_shows_the_review_state_and_the_cnpj(): void
    {
        $workshop = Workshop::factory()->pending()->create([
            'name' => 'Oficina Em Análise',
            'cnpj' => '12345678000190',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.workshops.index'))
            ->assertOk()
            ->assertSee('Oficina Em Análise')
            ->assertSee('Em análise')
            ->assertSee('12.345.678/0001-90')
            ->assertSee('data-workshop-row="'.$workshop->id.'"', escape: false);
    }

    public function test_the_default_tab_keeps_showing_every_workshop(): void
    {
        Workshop::factory()->pending()->create(['name' => 'Pendente Mecânica']);
        Workshop::factory()->create(['name' => 'Aprovada Mecânica']);

        $this->actingAs($this->admin())
            ->get(route('admin.workshops.index'))
            ->assertOk()
            ->assertSee('Pendente Mecânica')
            ->assertSee('Aprovada Mecânica');
    }

    public function test_the_pending_tab_filters_to_the_queue(): void
    {
        Workshop::factory()->pending()->create(['name' => 'Pendente Mecânica']);
        Workshop::factory()->create(['name' => 'Aprovada Mecânica']);

        $this->actingAs($this->admin())
            ->get(route('admin.workshops.index', ['filtro' => WorkshopStatus::Pending->value]))
            ->assertOk()
            ->assertSee('Pendente Mecânica')
            ->assertDontSee('Aprovada Mecânica');
    }

    public function test_admin_approves_a_workshop_and_the_account_is_notified(): void
    {
        Notification::fake();

        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;
        $workshop->forceFill(['status' => WorkshopStatus::Pending])->save();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.workshops.approve', $workshop))
            ->assertRedirect()
            ->assertSessionHas('success', "Oficina {$workshop->name} aprovada.");

        $workshop->refresh();

        $this->assertSame(WorkshopStatus::Approved, $workshop->status);
        $this->assertSame($admin->id, $workshop->reviewed_by);
        $this->assertNotNull($workshop->reviewed_at);

        Notification::assertSentTo(
            $user,
            WorkshopApprovalDecidedNotification::class,
            fn (WorkshopApprovalDecidedNotification $notification): bool => $notification->status === WorkshopStatus::Approved,
        );
    }

    public function test_admin_rejects_a_workshop_with_a_reason(): void
    {
        Notification::fake();

        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;
        $workshop->forceFill(['status' => WorkshopStatus::Pending])->save();

        $this->actingAs($this->admin())
            ->post(route('admin.workshops.reject', $workshop), ['review_notes' => 'CNPJ não encontrado.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $workshop->refresh();

        $this->assertSame(WorkshopStatus::Rejected, $workshop->status);
        $this->assertSame('CNPJ não encontrado.', $workshop->review_notes);

        Notification::assertSentTo(
            $user,
            WorkshopApprovalDecidedNotification::class,
            fn (WorkshopApprovalDecidedNotification $notification): bool => $notification->status === WorkshopStatus::Rejected,
        );
    }

    public function test_only_an_admin_decides(): void
    {
        $workshop = Workshop::factory()->pending()->create();

        // O middleware 'admin' manda embora com redirect, como em admin.garages.
        $this->actingAs(User::factory()->asUser()->create())
            ->post(route('admin.workshops.approve', $workshop))
            ->assertRedirect();

        $this->actingAs(User::factory()->asWorkshop()->create())
            ->post(route('admin.workshops.approve', $workshop))
            ->assertRedirect();

        $this->assertSame(WorkshopStatus::Pending, $workshop->fresh()->status);
    }
}
