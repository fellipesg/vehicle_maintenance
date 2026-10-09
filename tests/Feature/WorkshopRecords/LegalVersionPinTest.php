<?php

namespace Tests\Feature\WorkshopRecords;

use Tests\TestCase;

/**
 * A mudança de substância nos textos legais troca as versões (config/legal.php), que são gravadas
 * no aceite dos termos de cada veículo (user_vehicles.terms_version). Não força novo aceite.
 */
class LegalVersionPinTest extends TestCase
{
    public function test_versions_are_bumped_together_with_the_new_clauses(): void
    {
        $this->assertSame('2026-10-10', config('legal.terms_version'));
        $this->assertSame('2026-10-10', config('legal.privacy_version'));
    }

    public function test_terms_have_the_workshop_clause(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('6. Oficinas')
            ->assertSee('informou o cliente sobre o registro')
            ->assertSee('não vai colocar dados pessoais do cliente');
    }

    public function test_privacy_describes_workshop_records_on_vehicles_without_account(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Registros de oficinas em veículos sem conta')
            ->assertSee('legítimo interesse')
            ->assertSee('Anexos não aceitos em 90 dias')
            ->assertSee('direito de oposição')
            ->assertSee('marca irreversível (hash)')
            ->assertSee('do próprio celular dela');

        $this->getJson('/api/v1/legal/privacy-policy')->assertJsonPath('data.version', '2026-10-10');
        $this->getJson('/api/v1/legal/terms-of-use')->assertJsonPath('data.version', '2026-10-10');
    }
}
