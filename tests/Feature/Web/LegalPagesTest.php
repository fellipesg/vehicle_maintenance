<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_terms_page_is_public_and_uses_revisalog_copy(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Termos de uso')
            ->assertSee('Estes termos regem o uso da plataforma Revisalog')
            ->assertSee((string) config('legal.terms_version'))
            ->assertDontSee('Vehicle Maintenance');
    }

    public function test_privacy_page_is_public(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Política de privacidade')
            ->assertSee('Lei Geral de Proteção de Dados')
            ->assertSee((string) config('legal.support_email'));
    }

    public function test_api_terms_match_web_terms(): void
    {
        $this->getJson('/api/v1/legal/terms-of-use')
            ->assertOk()
            ->assertJsonPath('data.version', config('legal.terms_version'))
            ->assertJsonPath('data.content', config('legal.terms_of_use'));
    }

    public function test_privacy_page_renders_sections_lists_and_mail_links(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<h2', false)
            ->assertSee('7. Seus direitos')
            ->assertSee('<ul class="list-disc', false)
            ->assertSee('href="mailto:suporte@revisalog.com.br"', false)
            ->assertSee('em vigor desde 22 de setembro de 2026');
    }

    public function test_api_privacy_policy_matches_web_policy(): void
    {
        $this->getJson('/api/v1/legal/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.version', config('legal.privacy_version'))
            ->assertJsonPath('data.content', config('legal.privacy_policy'));
    }

    public function test_footer_shows_company_identification_when_configured(): void
    {
        config(['legal.company.legal_name' => 'Revisalog Tecnologia Ltda', 'legal.company.cnpj' => '00.000.000/0001-00']);

        $this->get(route('legal.terms'))
            ->assertSee('Revisalog Tecnologia Ltda')
            ->assertSee('CNPJ 00.000.000/0001-00');
    }
}
