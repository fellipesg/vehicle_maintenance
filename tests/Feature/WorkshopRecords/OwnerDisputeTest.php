<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceDisputedNotification;
use App\Services\FcmService;
use App\Services\Maintenance\MaintenanceOwnerDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Contestar é a ação que o dono atual tem SEM o CRLV-e, em qualquer OS de oficina.
 *
 * E contestar **marca, não esconde**: o registro continua em todas as superfícies, com o aviso de
 * que o proprietário contesta, porque o histórico pertence ao chassi e é o ativo do produto. Foi o
 * que substituiu o ocultar do proprietário, que sumia com o registro sem deixar sinal. Esconder de
 * verdade ficou com a equipe (MaintenancePublicVisibilityService).
 */
class OwnerDisputeTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    private const PLATE = 'QOS6H54';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->zeroOrMoreTimes();
    }

    public function test_owner_without_crlv_can_dispute_a_sealed_record_on_their_car(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();

        // O mesmo dono não consegue decidir: é exatamente o buraco que a contestação fecha.
        $this->assertFalse(app(MaintenanceOwnerDecisionService::class)->canDecide($owner, $record));
        $this->assertTrue(app(MaintenanceOwnerDecisionService::class)->canDispute($owner, $record));

        $this->actingAs($owner)
            ->post("/usuario/registros-de-oficinas/{$record->id}/contestar", [
                'reason' => 'Nunca levei o carro nesta oficina.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $record = $record->fresh();

        $this->assertTrue($record->isDisputed());
        $this->assertSame($owner->id, $record->owner_disputed_by_user_id);
        $this->assertSame('Nunca levei o carro nesta oficina.', $record->owner_dispute_reason);
        // Nada apagado, o selo intacto e nada escondido.
        $this->assertNotNull($record->verified_at);
        $this->assertNull($record->hidden_from_public_at);
        $this->assertDatabaseHas('maintenances', ['id' => $record->id]);
    }

    public function test_disputed_record_stays_in_the_public_search_marked(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();

        $this->assertCount(1, $this->publicMaintenances());

        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner, 'Nunca levei aqui.');

        $public = $this->publicMaintenances();

        $this->assertCount(1, $public, 'Contestar não tira o registro do chassi.');
        $this->assertTrue($public[0]['disputed']);
        // O texto do proprietário não vai para a busca aberta: só a marca.
        $this->assertArrayNotHasKey('dispute_reason', $public[0]);
    }

    public function test_the_mark_shows_on_the_vehicle_history_and_in_the_pdf_block(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();
        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner);

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-provenance-card :maintenance="$maintenance" />',
            ['maintenance' => $record->fresh()->load('workshop')],
        );

        $this->assertStringContainsString('Contestada pelo proprietário', $html);
    }

    public function test_a_sealed_record_carries_the_mark_on_the_public_seal_page(): void
    {
        Notification::fake();

        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshopUser, $vehicle);
        $owner = $this->ownerOf($vehicle, verified: false);

        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner);

        Auth::logout();
        $this->flushSession();

        // O selo continua autêntico e a página continua abrindo: o que muda é a ressalva.
        $this->get('/v/'.$record->verification_code)
            ->assertOk()
            ->assertSee('O proprietário contesta este registro')
            ->assertSee('data-verification-disputed', escape: false);
    }

    public function test_the_authoring_workshop_still_sees_the_disputed_record(): void
    {
        Notification::fake();

        [$owner, $workshopUser, $record] = $this->workshopRecordOnOwnedCar();

        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner);

        $this->actingAs($workshopUser)
            ->get("/oficina/manutencoes/{$record->id}")
            ->assertOk();
    }

    public function test_the_workshop_is_notified_so_it_can_fix_or_delete(): void
    {
        Notification::fake();

        [$owner, $workshopUser, $record] = $this->workshopRecordOnOwnedCar();

        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner, 'Chassi errado.');

        Notification::assertSentTo(
            $workshopUser,
            MaintenanceDisputedNotification::class,
            fn (MaintenanceDisputedNotification $notification): bool => $notification->reason === 'Chassi errado.'
                && $notification->maintenance->is($record),
        );
    }

    /**
     * O dono que chega depois e encontra no chassi dele uma OS de quando o carro não tinha dono:
     * antes só podia ocultar, e só com CRLV-e. Agora contesta, sem documento, e o registro fica.
     */
    public function test_a_record_from_before_the_owner_arrived_is_disputable_without_the_crlv(): void
    {
        Notification::fake();

        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshopUser, $vehicle);
        $owner = $this->ownerOf($vehicle, verified: false);

        $decisions = app(MaintenanceOwnerDecisionService::class);

        $this->assertTrue($record->isVerified());
        $this->assertFalse($decisions->canDecide($owner, $record));
        $this->assertTrue($decisions->canDispute($owner, $record));

        $this->actingAs($owner)
            ->post("/usuario/registros-de-oficinas/{$record->id}/contestar")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $record = $record->fresh();

        $this->assertTrue($record->isDisputed());
        $this->assertNotNull($record->verified_at, 'O selo não é revogado por uma contestação.');
        $this->assertNull($record->hidden_from_public_at, 'Contestar não esconde.');
    }

    /**
     * O que o proprietário declarou é dele: resolve editando ou excluindo, não contestando.
     */
    public function test_a_record_the_owner_declared_is_not_disputable(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create(['license_plate' => self::PLATE]);
        $owner = $this->ownerOf($vehicle);
        $own = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'registered_by_type' => 'owner',
        ]);

        $this->assertFalse(app(MaintenanceOwnerDecisionService::class)->canDispute($owner, $own));
    }

    public function test_someone_who_is_not_the_current_owner_cannot_dispute(): void
    {
        Notification::fake();

        [, , $record] = $this->workshopRecordOnOwnedCar();
        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger)
            ->post("/usuario/registros-de-oficinas/{$record->id}/contestar")
            ->assertForbidden();

        $this->assertFalse($record->fresh()->isDisputed());
    }

    public function test_owner_can_withdraw_the_dispute(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();

        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner);
        $this->assertTrue($this->publicMaintenances()[0]['disputed']);

        $this->actingAs($owner)
            ->delete("/usuario/registros-de-oficinas/{$record->id}/contestar")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $record = $record->fresh();

        $this->assertFalse($record->isDisputed());
        $this->assertNull($record->owner_disputed_by_user_id);
        $this->assertFalse($this->publicMaintenances()[0]['disputed']);
    }

    public function test_linking_a_pending_record_later_clears_the_dispute(): void
    {
        Notification::fake();

        // decide() é o fluxo do carro que não tinha dono: é lá que existe decisão pendente.
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshopUser, $vehicle);
        $owner = $this->ownerOf($vehicle, verified: true);

        $decisions = app(MaintenanceOwnerDecisionService::class);
        $decisions->dispute($record, $owner);
        $this->assertTrue($record->fresh()->isDisputed());

        $decisions->decide($record->fresh(), $owner, link: true, attachFiles: false);

        $record = $record->fresh();

        $this->assertFalse($record->isDisputed(), 'Vincular é aceitar: a contestação sai.');
        $this->assertSame(Maintenance::OWNER_LINKED, $record->owner_status);
        $this->assertNotNull($record->verified_at);
    }

    public function test_the_maintenance_detail_offers_the_dispute_without_the_crlv(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();

        $this->actingAs($owner)
            ->get("/usuario/manutencoes/{$record->id}")
            ->assertOk()
            ->assertSee('data-maintenance-dispute-form', escape: false)
            ->assertSee('Este serviço não foi feito no meu carro');
    }

    /**
     * No carro que ainda não tinha dono o registro aparece na fila de "Registros de oficinas", e a
     * contestação está lá também.
     */
    public function test_the_workshop_records_screen_offers_the_dispute_too(): void
    {
        Notification::fake();

        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($workshopUser, $vehicle);
        $owner = $this->ownerOf($vehicle, verified: false);

        $this->actingAs($owner)
            ->get('/usuario/registros-de-oficinas')
            ->assertOk()
            ->assertSee('data-record-dispute-form', escape: false);
    }

    public function test_the_api_disputes_without_the_crlv_and_reports_it(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();
        $this->actingAsApiUser($owner);

        // Num carro com dono o registro é uma manutenção comum do histórico dele: é no recurso de
        // manutenção que o app encontra can_dispute, não na fila de pendências.
        $this->getJson("/api/v1/maintenances/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.can_dispute', true)
            ->assertJsonPath('data.disputed', false);

        $this->postJson("/api/v1/maintenances/{$record->id}/dispute", ['reason' => 'Carro nunca esteve lá.'])
            ->assertOk()
            ->assertJsonPath('data.disputed', true)
            ->assertJsonPath('data.dispute_reason', 'Carro nunca esteve lá.');

        $this->deleteJson("/api/v1/maintenances/{$record->id}/dispute")
            ->assertOk()
            ->assertJsonPath('data.disputed', false);

        $this->assertFalse($record->fresh()->isDisputed());
    }

    public function test_the_api_refuses_to_dispute_what_the_owner_declared(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create(['license_plate' => self::PLATE]);
        $owner = $this->ownerOf($vehicle);
        $own = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'registered_by_type' => 'owner',
        ]);

        $this->actingAsApiUser($owner);

        $this->postJson("/api/v1/maintenances/{$own->id}/dispute")
            ->assertStatus(422)
            ->assertJsonPath('message', MaintenanceOwnerDecisionService::NOT_DISPUTABLE_MESSAGE);
    }

    /**
     * O fim da linha de uma contestação: a equipe confirma e esconde. É a única forma de um registro
     * sair da consulta pública agora.
     */
    public function test_only_the_team_can_take_the_record_out_of_the_public_search(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();
        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner, 'Fraude.');

        $this->assertCount(1, $this->publicMaintenances());

        $admin = User::factory()->asAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.maintenances.hide', $record), ['hidden_reason' => 'Chassi de outro veículo.'])
            ->assertRedirect();

        $record = $record->fresh();

        $this->assertNotNull($record->hidden_from_public_at);
        $this->assertSame($admin->id, $record->hidden_by_user_id);
        $this->assertSame('Chassi de outro veículo.', $record->hidden_reason);
        $this->assertSame([], $this->publicMaintenances());

        $this->actingAs($admin)->post(route('admin.maintenances.restore', $record))->assertRedirect();
        $this->assertCount(1, $this->publicMaintenances());
    }

    public function test_admin_sees_the_disputed_record_in_its_own_filter(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();
        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner);

        $admin = User::factory()->asAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('OS contestada');

        $this->actingAs($admin)
            ->get(route('admin.maintenances.index', ['contestadas' => '1']))
            ->assertOk()
            ->assertSee('Contestadas pelo proprietário');
    }

    /**
     * A oficina é a única que pode corrigir ou excluir a OS, então a contestação precisa estar na
     * tela dela, não só no e-mail.
     */
    public function test_the_workshop_sees_the_dispute_on_its_own_order_page(): void
    {
        Notification::fake();

        [$owner, $workshopUser, $record] = $this->workshopRecordOnOwnedCar();

        app(MaintenanceOwnerDecisionService::class)->dispute($record, $owner);

        $this->actingAs($workshopUser)
            ->get("/oficina/manutencoes/{$record->id}")
            ->assertOk()
            ->assertSee('Contestada pelo proprietário')
            ->assertSee('data-slot="provenance-disputed"', escape: false);
    }

    /**
     * Regressão: MaintenanceResource expõe provenance_card_label, que lê verifiedWorkshop. A
     * listagem da API não carregava a relação, e qualquer OS SELADA de oficina na lista virava
     * lazy load — exceção fora de produção, consulta por registro em produção. Nenhum teste
     * passava uma OS selada por aqui, e esta branch fez delas o caso comum.
     */
    public function test_the_api_list_renders_a_sealed_workshop_record_without_lazy_loading(): void
    {
        Notification::fake();

        [$owner, , $record] = $this->workshopRecordOnOwnedCar();

        $this->actingAsApiUser($owner);

        $this->getJson('/api/v1/maintenances?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $record->id)
            ->assertJsonPath('data.0.provenance_label', 'Selo da oficina');

        $this->getJson("/api/v1/maintenances/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.provenance_card_label', 'Selo da oficina · '.$record->workshop->name);
    }

    /**
     * Uma OS que uma oficina registrou num carro que já tem dono: nasce selada e no histórico dele.
     * O dono não tem CRLV-e, a menos que $verified seja true.
     *
     * @return array{0: User, 1: User, 2: Maintenance, 3: Vehicle}
     */
    private function workshopRecordOnOwnedCar(bool $verified = false): array
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => self::PLATE,
            'chassis' => '9BWZZZ377VT004999',
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);
        $owner = $this->ownerOf($vehicle, verified: $verified);
        $workshopUser = $this->workshopAccount();

        $this->actingAs($workshopUser)->post('/oficina/manutencoes', [
            'license_plate' => self::PLATE,
            'maintenance_type' => 'Retífica de motor',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 55_000,
            'service_category' => 'mechanical',
        ])->assertSessionHasNoErrors();

        $record = Maintenance::query()->latest('id')->firstOrFail();

        $this->assertNotNull($record->verified_at);

        return [$owner->fresh(), $workshopUser, $record, $vehicle];
    }

    /**
     * A busca pública como um visitante veria. Sai da sessão antes: a oficina autora e quem
     * contestou continuam vendo o registro, e é a visão de terceiro que interessa aqui.
     *
     * @return array<int, array<string, mixed>>
     */
    private function publicMaintenances(): array
    {
        Auth::logout();
        $this->flushSession();

        return $this->getJson('/api/v1/vehicles/search/'.self::PLATE)
            ->assertOk()
            ->json('data.maintenances') ?? [];
    }
}
