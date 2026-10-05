<?php

namespace Tests\Feature\Outreach;

use App\Enums\WorkshopProspectStatus;
use App\Jobs\ImportWorkshopProspectsCsv;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminOutreachTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asUser()->create(['is_admin' => true]);
    }

    public function test_only_admins_open_the_page(): void
    {
        $this->get(route('admin.outreach.index'))->assertRedirect();

        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('admin.outreach.index'))
            ->assertRedirect(route('user.dashboard'));

        $this->actingAs($this->admin())->get(route('admin.outreach.index'))->assertOk()->assertSee('Prospecção');
    }

    public function test_the_page_shows_funnel_state_limit_and_the_filterable_table(): void
    {
        config(['outreach.enabled' => true, 'outreach.daily_limit' => 7]);
        WorkshopProspect::factory()->create(['trade_name' => 'Auto Pendente', 'email' => 'pendente@x.com.br']);
        WorkshopProspect::factory()->sent()->create(['trade_name' => 'Auto Enviada', 'email' => 'enviada@x.com.br', 'cnpj' => '99999999000199']);

        $this->actingAs($this->admin())
            ->get(route('admin.outreach.index'))
            ->assertOk()
            ->assertSee('Pendentes')->assertSee('Descadastradas')->assertSee('Devolvidas')->assertSee('Falhas')
            ->assertSee('Envio ativo')
            ->assertSee('de 7 envios')
            ->assertSee('Auto Pendente')->assertSee('pendente@x.com.br')
            ->assertSee('Auto Enviada');

        $this->actingAs($this->admin())
            ->get(route('admin.outreach.index', ['situacao' => 'sent']))
            ->assertSee('Auto Enviada')->assertDontSee('Auto Pendente');

        $this->actingAs($this->admin())
            ->get(route('admin.outreach.index', ['q' => '99.999.999/0001-99']))
            ->assertSee('Auto Enviada')->assertDontSee('Auto Pendente');

        $this->actingAs($this->admin())
            ->get(route('admin.outreach.index', ['q' => 'pendente@']))
            ->assertSee('Auto Pendente')->assertDontSee('Auto Enviada');
    }

    public function test_pause_and_resume_toggle_the_cache_flag(): void
    {
        config(['outreach.enabled' => true]);
        Cache::forget('outreach.paused');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.outreach.pause'))->assertRedirect(route('admin.outreach.index'));
        $this->assertTrue(Cache::get('outreach.paused'));
        $this->actingAs($admin)->get(route('admin.outreach.index'))->assertSee('Envio parado')->assertSee('Retomar');

        $this->actingAs($admin)->post(route('admin.outreach.resume'));
        $this->assertNull(Cache::get('outreach.paused'));
    }

    public function test_csv_upload_is_queued_with_its_contents_and_returns_immediately(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $csv = implode("\n", [
            'cnpj,trade_name,email,phone,cnae,street,number,neighborhood,cep,city,state',
            '12345678000195,Auto Zé,ze@x.com.br,,4520001,,,,,LONDRINA,PR',
        ])."\n";
        $file = UploadedFile::fake()->createWithContent('prospects.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.outreach.import'), ['csv' => $file])
            ->assertRedirect(route('admin.outreach.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'na fila') && str_contains($message, $admin->email));

        Queue::assertPushed(ImportWorkshopProspectsCsv::class, fn (ImportWorkshopProspectsCsv $job): bool => $job->contents === $csv
            && $job->filename === 'prospects.csv'
            && $job->requestedById === $admin->id
            && $job->connection === 'database');
        $this->assertDatabaseCount('workshop_prospects', 0);
    }

    public function test_prospects_without_a_name_are_labelled_in_the_panel_not_as_sua_oficina(): void
    {
        WorkshopProspect::factory()->create(['trade_name' => null, 'legal_name' => null, 'cnpj' => '12345678000195']);

        $this->actingAs($this->admin())
            ->get(route('admin.outreach.index'))
            ->assertSee('Sem nome fantasia')
            ->assertSee('Ações para Oficina CNPJ 12.345.678/0001-95')
            ->assertDontSee('sua oficina');
    }

    public function test_csv_upload_validates_the_file_in_its_own_bag(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.outreach.import'), [])
            ->assertSessionHasErrorsIn('importProspects', 'csv');
    }

    public function test_row_actions_update_status_and_suppress_emails(): void
    {
        $admin = $this->admin();
        $prospect = WorkshopProspect::factory()->sent()->create(['email' => 'ze@x.com.br']);

        $this->actingAs($admin)->post(route('admin.outreach.replied', $prospect))->assertSessionHas('success');
        $this->assertSame(WorkshopProspectStatus::Replied, $prospect->fresh()->status);
        $this->assertNotNull($prospect->fresh()->replied_at);

        $this->actingAs($admin)->post(route('admin.outreach.converted', $prospect));
        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->fresh()->status);
        $this->assertNotNull($prospect->fresh()->converted_at);

        $this->actingAs($admin)->post(route('admin.outreach.bounced', $prospect));
        $this->assertSame(WorkshopProspectStatus::Bounced, $prospect->fresh()->status);
        $this->assertDatabaseHas('email_suppressions', ['email' => 'ze@x.com.br', 'reason' => 'bounced']);
    }

    public function test_manual_unsubscribe_writes_a_manual_suppression(): void
    {
        $prospect = WorkshopProspect::factory()->create(['email' => 'ze@x.com.br']);

        $this->actingAs($this->admin())->post(route('admin.outreach.unsubscribe', $prospect));

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Unsubscribed, $prospect->status);
        $this->assertNotNull($prospect->unsubscribed_at);
        $this->assertDatabaseHas('email_suppressions', ['email' => 'ze@x.com.br', 'reason' => 'manual']);
    }

    public function test_non_admins_cannot_use_the_actions(): void
    {
        $prospect = WorkshopProspect::factory()->create();

        $this->actingAs(User::factory()->asUser()->create())
            ->post(route('admin.outreach.unsubscribe', $prospect))
            ->assertRedirect(route('user.dashboard'));

        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_creating_a_workshop_with_a_prospect_email_converts_the_prospect(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create(['email' => 'ze@oficina.com.br']);
        $other = WorkshopProspect::factory()->create(['email' => 'outra@oficina.com.br']);

        $workshop = Workshop::factory()->create(['email' => 'Ze@Oficina.com.br']);

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->status);
        $this->assertSame($workshop->id, $prospect->workshop_id);
        $this->assertNotNull($prospect->converted_at);
        $this->assertSame(WorkshopProspectStatus::Pending, $other->fresh()->status);
    }
}
