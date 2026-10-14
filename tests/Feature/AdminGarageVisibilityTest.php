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

    /**
     * @return string o cabeçalho da ficha, onde "Não informado" noutros campos vazios da página não
     *                deve interferir na asserção.
     */
    private function accountHeader(User $user): string
    {
        $html = (string) $this->actingAs($this->admin())
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/data-slot="user-account-details".*?<\/dl>/s', $html, $header));

        return $header[0];
    }

    public function test_header_uses_the_store_contact_when_the_account_has_none(): void
    {
        $dealer = User::factory()->asGarage()->create(['phone' => null, 'city' => null, 'state' => null]);
        $dealer->garage->update(['phone' => '11999998888', 'city' => 'São Paulo', 'state' => 'SP']);

        $header = $this->accountHeader($dealer);

        $this->assertStringContainsString('11999998888', $header);
        $this->assertStringContainsString('São Paulo/SP', $header);
        $this->assertStringNotContainsString('Não informado', $header);
        $this->assertStringNotContainsString('Não informada', $header);
    }

    public function test_the_store_wins_over_the_account_when_both_are_filled(): void
    {
        // O app ainda grava endereço e telefone pessoais em users; eles não podem sombrear a loja
        // aqui, senão esta tela discorda da fila de análise e do e-mail ao proprietário.
        $dealer = User::factory()->asGarage()->create([
            'phone' => '11988887777',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);
        $dealer->garage->update(['phone' => '6733334444', 'city' => 'Campo Grande', 'state' => 'MS']);

        $header = $this->accountHeader($dealer);

        $this->assertStringContainsString('6733334444', $header);
        $this->assertStringContainsString('Campo Grande/MS', $header);
        $this->assertStringNotContainsString('11988887777', $header);
        $this->assertStringNotContainsString('São Paulo/SP', $header);
    }

    public function test_owner_header_keeps_using_the_account(): void
    {
        $owner = User::factory()->asUser()->create([
            'phone' => '11988887777',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $header = $this->accountHeader($owner);

        $this->assertStringContainsString('11988887777', $header);
        $this->assertStringContainsString('São Paulo/SP', $header);
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
