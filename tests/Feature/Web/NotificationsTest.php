<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\DocumentTitle;
use App\Support\NotificationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notificações: página, sino e "marcar como lida" usam o portal da conta para decidir se há uma
 * ficha de veículo para abrir, e nunca redirecionam para fora do site.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_opens_the_vehicle_in_the_owner_portal(): void
    {
        $owner = User::factory()->asUser()->create();
        $notification = $this->notify($owner, ['title' => 'Revisão dos 40.000 km', 'vehicle_id' => 42]);

        $this->actingAs($owner)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('user.vehicles.show', 42));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_dealer_opens_the_vehicle_in_the_stock(): void
    {
        $garage = User::factory()->asGarage()->create();
        $notification = $this->notify($garage, ['title' => 'Revisão', 'vehicle_id' => 7]);

        $this->actingAs($garage)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('garage.vehicles.show', 7));
    }

    public function test_workshop_is_never_sent_to_the_owner_vehicle_page(): void
    {
        $workshop = User::factory()->asWorkshop()->create();
        $notification = $this->notify($workshop, ['title' => 'Aviso', 'vehicle_id' => 7, 'vehicle_url' => '/usuario/veiculos/7']);

        $this->actingAs($workshop)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('workshop.dashboard'));

        $this->assertNull(NotificationLink::vehicleUrl($notification, $workshop));
    }

    public function test_notification_without_vehicle_goes_back_to_the_previous_page(): void
    {
        $owner = User::factory()->asUser()->create();
        $notification = $this->notify($owner, ['title' => 'Aviso da plataforma']);

        $this->actingAs($owner)
            ->from(route('user.maintenances.index'))
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('user.maintenances.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_only_internal_vehicle_urls_are_followed(): void
    {
        $owner = User::factory()->asUser()->create();
        $external = $this->notify($owner, ['title' => 'Externa', 'vehicle_url' => 'https://example.org/phishing']);
        $protocolRelative = $this->notify($owner, ['title' => 'Relativa ao protocolo', 'vehicle_url' => '//example.org/x']);
        $internal = $this->notify($owner, ['title' => 'Interna', 'vehicle_url' => '/usuario/veiculos/9']);
        $sameHost = $this->notify($owner, ['title' => 'Mesmo host', 'vehicle_url' => url('/usuario/veiculos/10')]);

        $this->assertNull(NotificationLink::vehicleUrl($external, $owner));
        $this->assertNull(NotificationLink::vehicleUrl($protocolRelative, $owner));
        $this->assertSame('/usuario/veiculos/9', NotificationLink::vehicleUrl($internal, $owner));
        $this->assertSame(url('/usuario/veiculos/10'), NotificationLink::vehicleUrl($sameHost, $owner));

        $this->actingAs($owner)
            ->post(route('notifications.read', $external->id))
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_cannot_mark_someone_elses_notification(): void
    {
        $owner = User::factory()->asUser()->create();
        $notification = $this->notify(User::factory()->asUser()->create(), ['title' => 'Alheia']);

        $this->actingAs($owner)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_returns_to_the_page_with_a_status_message(): void
    {
        $owner = User::factory()->asUser()->create();
        $this->notify($owner, ['title' => 'Primeira']);
        $this->notify($owner, ['title' => 'Segunda']);

        $this->actingAs($owner)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'Notificações marcadas como lidas.');

        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_index_distinguishes_unread_items_and_only_offers_the_vehicle_when_it_exists(): void
    {
        $owner = User::factory()->asUser()->create();
        $withVehicle = $this->notify($owner, ['title' => 'Revisão do Onix', 'body' => 'Faltam 500 km.', 'vehicle_id' => 5]);
        $this->notify($owner, ['title' => 'Aviso sem veículo']);
        $read = $this->notify($owner, ['title' => 'Lida com veículo', 'vehicle_id' => 6]);
        $read->markAsRead();

        $response = $this->actingAs($owner)->get(route('notifications.index'))->assertOk();
        $html = $response->getContent();

        $response
            ->assertSee('<title>Notificações · Proprietário · '.DocumentTitle::BRAND.'</title>', false)
            ->assertSee('Marcar todas como lidas')
            ->assertSee('<span class="sr-only">Não lida: </span>Revisão do Onix', false)
            ->assertSee('<span class="sr-only">Não lida: </span>Aviso sem veículo', false)
            ->assertDontSee('<span class="sr-only">Não lida: </span>Lida com veículo', false)
            ->assertSee('action="'.route('notifications.read', $withVehicle->id).'"', false)
            ->assertSee('href="'.route('user.vehicles.show', 6, absolute: false).'"', false);

        // Duas na página (a não lida com veículo e a lida com veículo) e uma no sino.
        $this->assertSame(3, substr_count($html, 'Ver veículo'));
        $this->assertSame(1, substr_count($html, 'Marcar como lida<'));
        $this->assertSame(2, substr_count($html, 'data-unread'));
    }

    public function test_index_shows_the_time_in_the_brasilia_timezone(): void
    {
        $owner = User::factory()->asUser()->create();
        $notification = $this->notify($owner, ['title' => 'Revisão']);
        $notification->forceFill(['created_at' => Carbon::parse('2026-03-19 01:30:00', 'UTC')])->saveQuietly();

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('>18/03/2026 22:30</time>', false)
            ->assertDontSee('19/03/2026 01:30');
    }

    public function test_index_shows_an_empty_state_without_the_mark_all_action(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nenhuma notificação ainda')
            ->assertDontSee('Marcar todas como lidas')
            ->assertDontSee('data-notification-list', false);
    }

    public function test_bell_counts_unread_items_caps_the_badge_and_hides_vehicle_links_for_workshops(): void
    {
        $workshop = User::factory()->asWorkshop()->create();

        foreach (range(1, 100) as $index) {
            $this->notify($workshop, ['title' => "Aviso {$index}", 'vehicle_id' => $index]);
        }

        $response = $this->actingAs($workshop)->get(route('workshop.dashboard'))->assertOk();

        $response
            ->assertSee('<span class="sr-only">Notificações, 100 não lidas</span>', false)
            ->assertSee('>99+</span>', false)
            ->assertDontSee('Ver veículo');
    }

    public function test_bell_shows_one_unread_item_in_the_singular(): void
    {
        $owner = User::factory()->asUser()->create();
        $this->notify($owner, ['title' => 'Revisão', 'vehicle_id' => 3]);

        $this->actingAs($owner)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('<span class="sr-only">Notificações, 1 não lida</span>', false)
            ->assertSee('Ver veículo');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, array $data): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MaintenanceKmReminderNotification',
            'data' => $data,
        ]);

        return $notification;
    }
}
