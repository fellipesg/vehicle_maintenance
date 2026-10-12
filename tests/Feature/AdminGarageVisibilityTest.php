<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A ficha do usuário no admin mostra a loja de quem é lojista, como já mostrava a oficina de quem
 * é oficina. Sem isso o admin via "Telefone: não informado" para uma loja que tinha acabado de
 * preencher tudo, porque esses dados moram em garages, não em users.
 */
class AdminGarageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asUser()->asAdmin()->create();
    }

    public function test_admin_sees_the_store_details_of_a_dealer(): void
    {
        $dealer = User::factory()->asGarage()->create();
        $dealer->garage->update([
            'name' => 'Loja do Dev',
            'document' => '12345678000190',
            'phone' => '11999998888',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', $dealer))
            ->assertOk()
            ->assertSee('Loja vinculada')
            ->assertSee('Loja do Dev')
            ->assertSee('12.345.678/0001-90')
            ->assertSee('11999998888')
            ->assertSee('Avenida Paulista, 1000 — Bela Vista — São Paulo/SP')
            ->assertSee('Aprovada');
    }

    public function test_admin_sees_the_status_and_the_review_note_of_a_rejected_store(): void
    {
        $dealer = User::factory()->asGarage()->create();
        $dealer->garage->forceFill([
            'status' => GarageStatus::Rejected,
            'review_notes' => 'CNPJ baixado na Receita.',
        ])->save();

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', $dealer))
            ->assertOk()
            ->assertSee('Recusada')
            ->assertSee('CNPJ baixado na Receita.');
    }

    public function test_a_cpf_stays_masked_but_a_cnpj_is_shown_in_full(): void
    {
        $dealer = User::factory()->asGarage()->create();

        // CNPJ é número público de registro: o admin precisa dele inteiro para conferir.
        $dealer->garage->update(['document' => '12345678000190']);
        $this->assertSame('12.345.678/0001-90', $dealer->garage->fresh()->displayDocument());

        // CPF é dado pessoal: sai mascarado.
        $dealer->garage->update(['document' => '37452845854']);
        $this->assertStringContainsString('•', (string) $dealer->garage->fresh()->displayDocument());
    }

    public function test_dealer_without_a_store_row_shows_an_empty_state_instead_of_breaking(): void
    {
        $dealer = User::factory()->asGarage()->create();
        $dealer->garage->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', $dealer->fresh()))
            ->assertOk()
            ->assertSee('Loja ainda não cadastrada');
    }

    public function test_header_falls_back_to_the_store_contact(): void
    {
        $dealer = User::factory()->asGarage()->create(['phone' => null, 'city' => null, 'state' => null]);
        $dealer->garage->update(['phone' => '11999998888', 'city' => 'São Paulo', 'state' => 'SP']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.show', $dealer))
            ->assertOk()
            ->getContent();

        // Só o cabeçalho: "Não informado" ainda é legítimo noutros campos vazios da página.
        $this->assertSame(1, preg_match('/data-slot="user-account-details".*?<\/dl>/s', (string) $html, $header));

        $this->assertStringContainsString('11999998888', $header[0]);
        $this->assertStringContainsString('São Paulo/SP', $header[0]);
        $this->assertStringNotContainsString('Não informado', $header[0]);
        $this->assertStringNotContainsString('Não informada', $header[0]);
    }

    public function test_owner_account_has_no_store_panel(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', $owner))
            ->assertOk()
            ->assertDontSee('Loja vinculada');
    }
}
