<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rótulo do pino nos mapas do admin: "Rua, número — cidade", sem vírgula nem travessão sobrando
 * quando algum pedaço do endereço está vazio.
 */
class AdminMapPinLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_labels_skip_empty_address_parts(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $cases = [
            'A completa' => [['street' => 'Rua das Flores', 'number' => '120', 'city' => 'Recife'], 'Rua das Flores, 120 — Recife'],
            'B sem número' => [['street' => 'Rua das Flores', 'number' => '', 'city' => 'Recife'], 'Rua das Flores — Recife'],
            'C só cidade' => [['street' => '', 'number' => ' ', 'city' => 'Olinda'], 'Olinda'],
            'D sem cidade' => [['street' => 'Av. Norte', 'number' => '9', 'city' => ''], 'Av. Norte, 9'],
            'E vazia' => [['street' => '', 'number' => '', 'city' => ''], ''],
        ];

        foreach ($cases as $name => [$address]) {
            Workshop::factory()->create(array_merge($address, ['name' => $name, 'latitude' => -8.05, 'longitude' => -34.9]));
        }

        $pins = collect($this->actingAs($admin)->get(route('admin.maps.workshops'))->assertOk()->viewData('pins'))
            ->pluck('label', 'name');

        foreach ($cases as $name => [, $label]) {
            $this->assertSame($label, $pins[$name], $name);
        }
    }

    public function test_owner_labels_use_the_same_format(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        User::factory()->asUser()->create(['name' => 'Ana', 'street' => 'Rua A', 'number' => '5', 'city' => 'Natal', 'latitude' => -5.8, 'longitude' => -35.2]);
        User::factory()->asUser()->create(['name' => 'Bia', 'street' => null, 'number' => '7', 'city' => null, 'latitude' => -5.8, 'longitude' => -35.2]);

        $pins = collect($this->actingAs($admin)->get(route('admin.maps.users'))->assertOk()->viewData('pins'))
            ->pluck('label', 'name');

        $this->assertSame('Rua A, 5 — Natal', $pins['Ana']);
        $this->assertSame('7', $pins['Bia']);
    }
}
