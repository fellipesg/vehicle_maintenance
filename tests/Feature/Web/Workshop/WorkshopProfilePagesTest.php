<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\User;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Minha oficina (dl com tel:, wa.me e mailto:, campos vazios ocultos, prévia do selo e o que falta)
 * e o formulário da oficina (seções, máscaras, UF em lista e normalização de CEP, telefone e
 * redes antes de validar).
 */
class WorkshopProfilePagesTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_profile_shows_formatted_contact_links_and_hides_empty_fields(): void
    {
        $user = $this->workshopUser();
        $user->workshop->update([
            'name' => 'Mecânica Boa Vista',
            'phone' => '11987654321',
            'whatsapp' => '1133334444',
            'email' => 'contato@boavista.test',
            'instagram' => 'https://www.instagram.com/boavista',
            'facebook' => null,
            'cep' => '01310100',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.profile.show')));

        $this->assertSingleH1($xpath, 'Minha oficina');
        $contact = $this->element($xpath, '//*[@data-profile-contact]');
        $this->assertSame(1, $this->countNodes($xpath, './/dl', $contact));
        $this->assertSame('(11) 98765-4321', $this->text($this->element($xpath, './/a[@href="tel:+5511987654321"]', $contact)));
        $whatsapp = $this->element($xpath, './/a[@href="https://wa.me/551133334444"]', $contact);
        $this->assertStringContainsString('(11) 3333-4444', $this->text($whatsapp));
        $this->assertStringContainsString('noopener', $whatsapp->getAttribute('rel'));
        $this->assertSame(1, $this->countNodes($xpath, './/a[@href="mailto:contato@boavista.test"]', $contact));

        $this->assertStringContainsString('01310-100', $this->text($this->element($xpath, '//*[@data-profile-address]')));
        $social = $this->element($xpath, '//*[@data-profile-social]');
        $this->assertStringContainsString('instagram.com/boavista', $this->text($social));
        $this->assertStringNotContainsString('Facebook', $this->text($social));

        $preview = $this->element($xpath, '//*[@data-seal-preview]');
        $this->assertStringContainsString('Mecânica Boa Vista', $this->text($preview));
        $this->assertStringContainsString('Selo da oficina', $this->text($preview));
    }

    public function test_missing_whatsapp_is_not_an_empty_label_and_shows_in_the_checklist(): void
    {
        $user = $this->workshopUser();
        $user->workshop->update(['whatsapp' => null, 'email' => null, 'instagram' => null, 'facebook' => null, 'logo_path' => null]);

        $response = $this->actingAs($user)->get(route('workshop.profile.show'));
        $xpath = $this->page($response);

        $this->assertStringNotContainsString('WhatsApp', $this->text($this->element($xpath, '//*[@data-profile-contact]')));
        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-profile-social]'));
        $checklist = $this->element($xpath, '//*[@data-profile-checklist]');
        $this->assertStringContainsString('0 de 5 itens preenchidos', $this->text($checklist));
        $this->assertStringContainsString('WhatsApp para os clientes', $this->text($checklist));
        $this->assertSame(route('workshop.warranty-templates.create'), $this->element($xpath, './/li[contains(., "Modelo de garantia ativo")]//a', $checklist)->getAttribute('href'));
    }

    public function test_complete_profile_checklist(): void
    {
        $user = $this->workshopUser();
        $user->workshop->update(['whatsapp' => '11999999999', 'email' => 'a@b.test', 'instagram' => 'https://www.instagram.com/x', 'logo_path' => 'workshop-logos/x.png']);
        WarrantyTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => true]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.profile.show')));

        $this->assertStringContainsString('5 de 5 itens preenchidos', $this->text($this->element($xpath, '//*[@data-profile-checklist]')));
        $logo = $this->element($xpath, '//*[@data-profile-identity]//img');
        $this->assertStringContainsString('object-contain', $logo->getAttribute('class'));
    }

    public function test_form_uses_sections_masks_and_state_select(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.profile.edit')));

        $this->assertSingleH1($xpath, 'Editar oficina');
        $legends = array_map(fn (\DOMElement $legend): string => $this->text($legend), iterator_to_array($xpath->query('//form[@data-workshop-profile-form]//fieldset[@data-slot="form-section"]/legend')));
        $this->assertSame(['Identidade', 'Contato', 'Endereço', 'Redes sociais'], $legends);

        $phone = $this->element($xpath, '//input[@name="phone"]');
        $this->assertSame('tel', $phone->getAttribute('type'));
        $this->assertSame('digits', $phone->getAttribute('data-mask'));
        $this->assertSame('10', $phone->getAttribute('data-min-digits'));

        $cep = $this->element($xpath, '//input[@name="cep"]');
        $this->assertSame('digits', $cep->getAttribute('data-mask'));
        $this->assertSame('8', $cep->getAttribute('data-max-digits'));
        $this->assertFalse($cep->hasAttribute('maxlength'), 'Sem maxlength: "01310-100" não é cortado.');

        $state = $this->element($xpath, '//select[@name="state"]');
        $this->assertSame(28, $this->countNodes($xpath, './option', $state), '27 UFs e a opção vazia.');
        $this->assertTrue($this->element($xpath, './option[@value="'.$user->workshop->state.'"]', $state)->hasAttribute('selected'));

        $this->assertSame('text', $this->element($xpath, '//input[@name="instagram"]')->getAttribute('type'));
        $this->assertSame(route('workshop.profile.show'), $this->element($xpath, '//*[@data-slot="form-actions"]//a[normalize-space()="Cancelar"]')->getAttribute('href'));
    }

    public function test_store_normalizes_cep_phones_state_and_social_handles(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->delete();
        $user->unsetRelation('workshop');

        $this->actingAs($user)->post(route('workshop.profile.store'), [
            'name' => 'Oficina Nova',
            'phone' => '(11) 98888-7777',
            'cep' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '900',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'sp',
            'instagram' => '@oficinanova',
            'facebook' => 'facebook.com/oficinanova',
        ])->assertSessionHasNoErrors()->assertRedirect(route('workshop.dashboard'));

        $this->assertDatabaseHas('workshops', [
            'user_id' => $user->id,
            'phone' => '11988887777',
            'whatsapp' => '11988887777',
            'cep' => '01310100',
            'state' => 'SP',
            'instagram' => 'https://www.instagram.com/oficinanova',
            'facebook' => 'https://www.facebook.com/oficinanova',
        ]);
    }

    public function test_invalid_state_and_short_phone_are_refused_in_portuguese(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)->put(route('workshop.profile.update'), [
            'state' => 'XX',
            'phone' => '1234',
        ])->assertSessionHasErrors([
            'state' => 'Escolha a UF da lista.',
            'phone' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
        ]);
    }
}
