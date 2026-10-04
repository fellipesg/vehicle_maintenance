<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * /api/v1/notifications: o sino da conta no app (lista, contador e marcar como lida).
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->asUser()->create();
        $this->token = $this->user->createToken(SanctumMobileToken::TOKEN_NAME, SanctumMobileToken::ABILITIES)->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, array $data, bool $read = false): string
    {
        $id = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\Test',
            'data' => $data,
            'read_at' => $read ? now() : null,
        ]);

        return $id;
    }

    private function api(): self
    {
        return $this->withToken($this->token);
    }

    public function test_lists_notifications_without_web_only_keys(): void
    {
        $this->notify($this->user, [
            'type' => 'workshop-review-decided',
            'title' => 'Dev Oficina confirmou o seu serviço',
            'body' => 'A manutenção agora tem o Selo da oficina.',
            'maintenance_id' => 12,
            'vehicle_id' => 3,
            'status' => 'confirmed',
            'action_url' => '/oficina/validacoes',
            'action_label' => 'Validar serviço',
        ]);

        $response = $this->api()->getJson('/api/v1/notifications')->assertOk();

        $response->assertJsonPath('data.0.type', 'workshop-review-decided')
            ->assertJsonPath('data.0.title', 'Dev Oficina confirmou o seu serviço')
            ->assertJsonPath('data.0.maintenance_id', 12)
            ->assertJsonPath('data.0.vehicle_id', 3)
            ->assertJsonPath('data.0.data.status', 'confirmed')
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonMissingPath('data.0.data.action_url')
            ->assertJsonMissingPath('data.0.data.action_label')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_does_not_list_other_users_notifications(): void
    {
        $this->notify(User::factory()->asUser()->create(), ['title' => 'De outra conta']);

        $this->api()->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_unread_filter_and_count(): void
    {
        $this->notify($this->user, ['title' => 'Lida'], read: true);
        $this->notify($this->user, ['title' => 'Nova']);

        $this->api()->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Nova');

        $this->api()->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_marks_one_notification_as_read(): void
    {
        $id = $this->notify($this->user, ['title' => 'Nova']);

        $this->api()->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertNotNull($this->user->notifications()->find($id)->read_at);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $other = User::factory()->asUser()->create();
        $id = $this->notify($other, ['title' => 'De outra conta']);

        $this->api()->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();

        $this->assertNull($other->notifications()->find($id)->read_at);
    }

    public function test_marks_all_as_read(): void
    {
        $this->notify($this->user, ['title' => 'A']);
        $this->notify($this->user, ['title' => 'B']);

        $this->api()->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_token_without_profile_ability_is_rejected(): void
    {
        $limited = $this->user->createToken('limited', ['vehicles:read'])->plainTextToken;

        $this->withToken($limited)->getJson('/api/v1/notifications')->assertForbidden();
    }
}
