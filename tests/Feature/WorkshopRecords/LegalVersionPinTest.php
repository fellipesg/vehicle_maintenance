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
        $this->assertSame('2026-11-18', config('legal.terms_version'));
        $this->assertSame('2026-11-25', config('legal.privacy_version'));
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

        $this->getJson('/api/v1/legal/privacy-policy')->assertJsonPath('data.version', '2026-11-25');
        $this->getJson('/api/v1/legal/terms-of-use')->assertJsonPath('data.version', '2026-11-18');
    }

    public function test_privacy_discloses_the_meta_sdk_in_the_ios_app(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Aplicativo iOS e medição de anúncios')
            ->assertSee('SDK da Meta (Facebook)')
            ->assertSee('nunca placa, chassi, RENAVAM, nome, e-mail nem documentos')
            ->assertSee('identificador de publicidade do aparelho (IDFA)')
            ->assertSee('a medição continua sem o IDFA')
            ->assertSee('Ajustes → Privacidade e Segurança → Rastreamento')
            ->assertSee('Meta (SDK do Facebook no aplicativo iOS, para medir os anúncios da RevisaLog)')
            ->assertSee('Não usamos os dados dessa medição do site para publicidade')
            ->assertDontSee('Não usamos esses dados para publicidade');
    }
}
