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
            ->assertSee('Termos de Uso — Revisalog')
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
}
