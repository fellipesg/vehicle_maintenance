<?php

namespace Tests\Feature\Web;

use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * Oficinas da rede renderizadas no servidor: busca na URL, contato clicável (tel:, WhatsApp,
 * mailto:), o Selo da oficina e "Registrar manutenção aqui".
 */
class OwnerWorkshopDirectoryTest extends TestCase
{
    use InspectsOwnerPages;
    use RefreshDatabase;

    public function test_directory_lists_workshops_with_clickable_contacts_and_the_register_action(): void
    {
        $workshop = Workshop::factory()->create([
            'name' => 'Auto Center Londrina',
            'phone' => '43999998888',
            'whatsapp' => '4333334444',
            'email' => 'contato@autocenter.test',
            'neighborhood' => 'Centro',
            'city' => 'Londrina',
            'state' => 'PR',
        ]);

        $xpath = $this->ownerPage($this->actingAs($this->owner())->get(route('user.workshops.index')));

        $this->assertOnlyHeading($xpath, 'Oficinas da rede');
        $card = $this->ownerElement($xpath, '//*[@data-workshops-list]/li[@data-workshop-id="'.$workshop->id.'"]');
        $text = $this->ownerText($card);
        $this->assertStringContainsString('Auto Center Londrina', $text);
        $this->assertStringContainsString('Centro · Londrina/PR', $text);
        $this->assertStringContainsString('(43) 99999-8888', $text);
        $this->assertStringContainsString('Emite Selo da oficina', $text);

        $this->ownerElement($xpath, './/a[@href="tel:+5543999998888"]', $card);
        $whatsapp = $this->ownerElement($xpath, './/a[@href="https://wa.me/554333334444"]', $card);
        $this->assertSame('_blank', $whatsapp->getAttribute('target'));
        $this->assertSame('noopener', $whatsapp->getAttribute('rel'));
        $this->ownerElement($xpath, './/a[@href="mailto:contato@autocenter.test"]', $card);
        $this->ownerElement($xpath, './/a[@href="'.route('user.maintenances.create', ['workshop_id' => $workshop->id]).'"]', $card);
        $this->assertStringNotContainsString('Carregando', $this->ownerText($this->ownerElement($xpath, '//main')));
    }

    public function test_search_is_a_labelled_get_form_that_matches_name_city_or_neighborhood(): void
    {
        Workshop::factory()->create(['name' => 'Oficina do Zé', 'city' => 'Curitiba', 'neighborhood' => 'Batel']);
        Workshop::factory()->create(['name' => 'Mecânica Norte', 'city' => 'Maringá', 'neighborhood' => 'Zona 7']);
        $owner = $this->owner();

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.workshops.index', ['busca' => 'batel'])));

        $form = $this->ownerElement($xpath, '//main//form[@role="search"]');
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $input = $this->ownerElement($xpath, './/input[@id="workshop-search"][@name="busca"]', $form);
        $this->assertSame('batel', $input->getAttribute('value'));
        $this->ownerElement($xpath, './/label[@for="workshop-search"]', $form);
        $this->ownerElement($xpath, './/a[@href="'.route('user.workshops.index').'"]', $form);

        $cards = $this->ownerElements($xpath, '//*[@data-workshops-list]/li');
        $this->assertCount(1, $cards);
        $this->assertStringContainsString('Oficina do Zé', $this->ownerText($cards[0]));
        $this->assertStringContainsString('1 oficina encontrada para “batel”', $this->ownerText($this->ownerElement($xpath, '//*[@data-workshops-count]')));

        // Links antigos com ?search= continuam funcionando.
        $legacy = $this->ownerPage($this->actingAs($owner)->get(route('user.workshops.index', ['search' => 'MARINGÁ'])));
        $this->assertCount(1, $this->ownerElements($legacy, '//*[@data-workshops-list]/li'));
    }

    public function test_search_without_results_offers_to_clear_it(): void
    {
        Workshop::factory()->create(['name' => 'Oficina Central']);

        $xpath = $this->ownerPage($this->actingAs($this->owner())->get(route('user.workshops.index', ['busca' => 'inexistente'])));

        $empty = $this->ownerElement($xpath, '//main//*[@data-slot="empty-state"]');
        $this->assertStringContainsString('Nenhuma oficina para “inexistente”', $this->ownerText($empty));
        $this->ownerElement($xpath, './/a[@href="'.route('user.workshops.index').'"]', $empty);
    }

    public function test_directory_is_paginated(): void
    {
        Workshop::factory()->count(26)->create();

        $xpath = $this->ownerPage($this->actingAs($this->owner())->get(route('user.workshops.index')));

        $this->assertCount(24, $this->ownerElements($xpath, '//*[@data-workshops-list]/li'));
        $this->ownerElement($xpath, '//main//a[contains(@href, "page=2")]');
    }
}
