<?php

namespace Tests\Feature\Web;

use App\Enums\Portal;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AppStorage;
use App\Support\DocumentMask;
use App\Support\Vehicle\VehicleEntryFlow;
use Database\Seeders\VehicleCatalogSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Assistente único de entrada de veículo: "Adicionar veículo" (Proprietário) e "Adicionar ao
 * estoque" (Lojista). Passo 1 Documento (CRLV-e primeiro, manual recolhido), passo 2 Conferir
 * (veículo novo ou vínculo), procuração como passo condicional do lojista e passo 3 Capas.
 */
class VehicleEntryWizardTest extends TestCase
{
    use RefreshDatabase;

    private const HONDA_OWNER_DOCUMENT = '374.528.458-54';

    private User $owner;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
        $this->owner = User::factory()->asUser()->create();
        $this->garage = User::factory()->asGarage()->create(['document' => null]);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function portalProvider(): array
    {
        return [
            'proprietário' => ['owner', 'user', 'Adicionar veículo', 'Meus veículos'],
            'lojista' => ['garage', 'garage', 'Adicionar ao estoque', 'Estoque'],
        ];
    }

    #[DataProvider('portalProvider')]
    public function test_document_step_puts_the_crlv_first_and_the_manual_form_collapsed(string $actor, string $prefix, string $title, string $listLabel): void
    {
        $flow = $this->flowFor($prefix);
        $page = $this->page($this->actingAs($this->{$actor})->get($flow->url('create'))->assertOk()->getContent());

        $this->assertSingleH1($page, $title);
        $this->assertStringContainsString($title, $page->querySelector('title')->textContent);
        $this->assertSame($flow->listUrl(), $page->querySelector('nav[aria-label="Trilha"] a')->getAttribute('href'));
        $this->assertSame($listLabel, trim($page->querySelector('nav[aria-label="Trilha"] a')->textContent));
        $this->assertCurrentStep($page, 1, 3, 'Documento');

        $crlvForm = $page->querySelector('section[data-crlv-import] form');
        $this->assertSame($flow->url('import-crlv'), $crlvForm->getAttribute('action'));
        $this->assertSame('multipart/form-data', $crlvForm->getAttribute('enctype'));
        $this->assertStringContainsString('Recomendado', $page->querySelector('section[data-crlv-import]')->textContent);

        $file = $crlvForm->querySelector('input[type="file"][name="crlv"]');
        $this->assertTrue($file->hasAttribute('required'));
        $this->assertStringContainsString('application/pdf', $file->getAttribute('accept'));
        $this->assertSame((string) (10 * 1024 * 1024), $crlvForm->querySelector('[data-file-input]')->getAttribute('data-max-bytes'));
        $this->assertStringContainsString('até 10 MB', $crlvForm->querySelector('#crlv-rules')->textContent);

        $read = $crlvForm->querySelector('button[type="submit"]');
        $this->assertSame('Ler CRLV-e', trim($read->textContent));
        $this->assertSame('Lendo CRLV-e…', $read->getAttribute('data-loading-label'));
        $this->assertSame('primary', $read->getAttribute('data-variant'));

        $manual = $page->querySelector('details[data-vehicle-entry-manual]');
        $this->assertFalse($manual->hasAttribute('open'), 'O formulário manual começa recolhido.');
        $this->assertStringContainsString('preencher manualmente', $manual->querySelector('summary')->textContent);
        $this->assertSame($flow->url('store'), $manual->querySelector('form')->getAttribute('action'));
        $this->assertNotNull($manual->querySelector('[data-terms-scroll-accept]'), 'O aceite fica no formulário que grava o veículo.');
        $this->assertSame($title, trim($manual->querySelector('button[data-terms-submit]')->textContent));

        $this->assertStringNotContainsString('Vincular com CRLV-e', $page->body->textContent);
        $this->assertNull($page->querySelector('a[href="'.route($prefix.'.vehicles.claim').'"]'), 'O vínculo não tem mais tela própria.');
    }

    #[DataProvider('portalProvider')]
    public function test_old_claim_urls_redirect_to_the_document_step_keeping_the_notices(string $actor, string $prefix): void
    {
        $flow = $this->flowFor($prefix);

        $this->assertStringEndsWith('/vincular', route($prefix.'.vehicles.claim'), 'O nome de rota antigo continua valendo.');

        $this->actingAs($this->{$actor})
            ->withSession(['warning' => 'Aviso que veio de antes.', '_flash' => ['old' => ['warning'], 'new' => []]])
            ->get(route($prefix.'.vehicles.claim'))
            ->assertRedirect($flow->url('create'));

        $this->actingAs($this->{$actor})
            ->get($flow->url('create'))
            ->assertOk()
            ->assertSee('Aviso que veio de antes.');
    }

    #[DataProvider('portalProvider')]
    public function test_invalid_crlv_returns_to_the_document_step_with_the_error_on_the_field(string $actor, string $prefix): void
    {
        $flow = $this->flowFor($prefix);

        $this->actingAs($this->{$actor})
            ->post($flow->url('import-crlv'), ['crlv' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')])
            ->assertRedirect($flow->url('create'))
            ->assertSessionHasErrors('crlv');

        $page = $this->page($this->actingAs($this->{$actor})->get($flow->url('create'))->assertOk()->getContent());

        $file = $page->querySelector('input[name="crlv"]');
        $this->assertSame('true', $file->getAttribute('aria-invalid'));
        $this->assertContains('crlv-error', explode(' ', (string) $file->getAttribute('aria-describedby')));
        $this->assertNotSame('', trim($page->querySelector('#crlv-error')->textContent));
        $this->assertFalse($page->querySelector('details[data-vehicle-entry-manual]')->hasAttribute('open'), 'Erro do CRLV-e não abre o manual.');
    }

    public function test_missing_crlv_file_has_a_portuguese_message(): void
    {
        $this->actingAs($this->owner)
            ->post(route('user.vehicles.import-crlv'), [])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHasErrors(['crlv' => 'Escolha o PDF do CRLV-e para continuar.']);
    }

    public function test_new_vehicle_path_reviews_the_crlv_and_goes_on_to_the_covers(): void
    {
        $this->actingAs($this->owner)
            ->post(route('user.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()])
            ->assertRedirect(route('user.vehicles.import.preview'));

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.import.preview'))->assertOk()->getContent());

        $this->assertSingleH1($page, 'Conferir dados do veículo');
        $this->assertSame('Novo veículo', trim($page->querySelector('[data-slot="page-header-eyebrow"]')->textContent));
        $this->assertCurrentStep($page, 2, 3, 'Conferir');
        $this->assertNull($page->querySelector('[data-ownership-notice]'), 'O proprietário sempre fica como dono: sem aviso de posse.');

        $summary = $page->querySelector('[data-crlv-summary]')->textContent;
        $this->assertStringContainsString('PHF9J95', $summary);
        $this->assertStringContainsString('01050047521', $summary);
        $this->assertStringContainsString('RODRIGO SANCHES DEVIGO · •••.528.458-••', $summary);
        $this->assertStringNotContainsString(self::HONDA_OWNER_DOCUMENT, $page->body->textContent, 'O CPF do proprietário sai parcial.');
        $this->assertStringNotContainsString('CPF/CNPJ da sua conta', $summary);

        $form = $page->querySelector('form[data-vehicle-entry-form="review"]');
        $this->assertSame(route('user.vehicles.store'), $form->getAttribute('action'));
        $this->assertSame(session('crlv_verification.token'), $form->querySelector('input[name="crlv_verification_token"]')->getAttribute('value'));
        $this->assertSame('PHF9J95', $form->querySelector('input[name="license_plate"]')->getAttribute('value'));
        $this->assertNotNull($form->querySelector('input[name="terms_accepted"]'));
        $this->assertSame('Adicionar veículo', trim($form->querySelector('button[data-terms-submit]')->textContent));

        $response = $this->actingAs($this->owner)->post(route('user.vehicles.store'), $this->hondaPayload());
        $vehicle = Vehicle::where('license_plate', 'PHF9J95')->firstOrFail();

        $response->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHas('success', 'Veículo adicionado à sua conta.')
            ->assertSessionMissing('crlv_verification');

        $this->assertTrue($this->owner->vehicles()->whereKey($vehicle->id)->exists());
    }

    #[DataProvider('portalProvider')]
    public function test_covers_step_offers_both_croppers_and_a_way_to_skip(string $actor, string $prefix): void
    {
        $flow = $this->flowFor($prefix);
        $vehicle = $this->vehicleOwnedBy($this->{$actor}, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'PHF9J95']);

        $page = $this->page($this->actingAs($this->{$actor})->get($flow->url('covers', $vehicle))->assertOk()->getContent());

        $this->assertSingleH1($page, 'Adicionar capas');
        $this->assertStringContainsString('Honda Civic · PHF9J95', $page->querySelector('[data-slot="page-header-description"]')->textContent);
        $this->assertCurrentStep($page, 3, 3, 'Capas');

        $form = $page->querySelector('form[data-vehicle-entry-form="covers"]');
        $this->assertSame($flow->url('covers.update', $vehicle), $form->getAttribute('action'));
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));
        $this->assertSame('PUT', $form->querySelector('input[name="_method"]')->getAttribute('value'));

        $landscape = $form->querySelector('[data-image-cropper][data-aspect="16:9"]');
        $portrait = $form->querySelector('[data-image-cropper][data-aspect="9:16"]');
        $this->assertNotNull($landscape->querySelector('input[type="file"][name="cover"]'));
        $this->assertNotNull($portrait->querySelector('input[type="file"][name="cover_portrait"]'));
        $this->assertSame((string) (5 * 1024 * 1024), $landscape->getAttribute('data-max-bytes'));

        $skip = $form->querySelector('a[data-skip-covers]');
        $this->assertSame($flow->vehicleUrl($vehicle), $skip->getAttribute('href'));
        $this->assertSame('Pular por agora', trim($skip->textContent));
        $this->assertSame('Enviando capas…', $form->querySelector('button[type="submit"]')->getAttribute('data-loading-label'));
    }

    #[DataProvider('portalProvider')]
    public function test_covers_step_saves_the_covers_and_opens_the_vehicle(string $actor, string $prefix): void
    {
        $this->fakeCoversDisk('r2');
        $flow = $this->flowFor($prefix);
        $vehicle = $this->vehicleOwnedBy($this->{$actor});

        $this->actingAs($this->{$actor})
            ->put($flow->url('covers.update', $vehicle), [
                'cover' => UploadedFile::fake()->image('capa-recorte.jpg', 1600, 900),
                'cover_portrait' => UploadedFile::fake()->image('retrato-recorte.jpg', 900, 1600),
            ])
            ->assertRedirect($flow->vehicleUrl($vehicle))
            ->assertSessionHas('success', 'Capas salvas.');

        $vehicle->refresh();
        $this->assertNotNull($vehicle->cover_photo_path);
        $this->assertNotNull($vehicle->cover_photo_portrait_path);
        $this->assertNotNull($vehicle->cover_photo_thumb_path);
    }

    public function test_covers_step_asks_for_an_image_and_rejects_other_files(): void
    {
        $vehicle = $this->vehicleOwnedBy($this->owner);

        $this->actingAs($this->owner)
            ->from(route('user.vehicles.covers', $vehicle))
            ->put(route('user.vehicles.covers.update', $vehicle), [])
            ->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHasErrors(['cover' => 'Escolha ao menos uma capa, ou use "Pular por agora".']);

        $this->actingAs($this->owner)
            ->put(route('user.vehicles.covers.update', $vehicle), [
                'cover_portrait' => UploadedFile::fake()->create('capa.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('cover_portrait');

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.covers', $vehicle))->getContent());
        $this->assertSame('true', $page->querySelector('input[name="cover_portrait"]')->getAttribute('aria-invalid'));
    }

    public function test_covers_step_is_only_for_who_can_edit_the_vehicle(): void
    {
        $vehicle = $this->vehicleOwnedBy(User::factory()->asUser()->create());

        $this->actingAs($this->owner)->get(route('user.vehicles.covers', $vehicle))->assertForbidden();
        $this->actingAs($this->owner)
            ->put(route('user.vehicles.covers.update', $vehicle), ['cover' => UploadedFile::fake()->image('capa.jpg')])
            ->assertForbidden();
        $this->actingAs($this->garage)->get(route('garage.vehicles.covers', $vehicle))->assertForbidden();
        $this->actingAs($this->owner)->get(route('garage.vehicles.covers', $vehicle))->assertRedirect(route('user.dashboard'));
    }

    public function test_manual_entry_goes_to_the_covers_with_its_own_message(): void
    {
        $response = $this->actingAs($this->owner)->post(route('user.vehicles.store'), [
            'license_plate' => 'ABC1D23',
            'renavam' => '12345678901',
            'crv_number' => '987654321098',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'chassis' => '9BWZZZ377VT004277',
            'current_kilometers' => 42000,
            'terms_accepted' => '1',
        ]);

        $vehicle = Vehicle::where('license_plate', 'ABC1D23')->firstOrFail();

        $response->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHas('success', 'Veículo adicionado. Para confirmar a propriedade, importe o CRLV-e depois, em Editar veículo.');
    }

    public function test_manual_entry_of_a_vehicle_already_in_revisalog_points_to_the_crlv(): void
    {
        Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004277']);

        $this->actingAs($this->owner)
            ->from(route('user.vehicles.create'))
            ->post(route('user.vehicles.store'), [
                'license_plate' => 'ABC1D23',
                'renavam' => '12345678901',
                'crv_number' => '987654321098',
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2020,
                'chassis' => '9bwzzz377vt004277',
                'current_kilometers' => 42000,
                'terms_accepted' => '1',
            ])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHas('vehicle_exists', true)
            ->assertSessionHasErrors(['chassis' => 'Já existe um veículo com este chassi na RevisaLog. Envie o CRLV-e dele para vinculá-lo à sua conta.']);

        $this->assertSame(1, Vehicle::count());

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.create'))->assertOk()->getContent());

        $this->assertStringContainsString('Este veículo já está na RevisaLog', $page->querySelector('[data-vehicle-exists]')->textContent);
        $this->assertTrue($page->querySelector('details[data-vehicle-entry-manual]')->hasAttribute('open'), 'O manual reabre com o que foi digitado.');
        $this->assertSame('ABC1D23', $page->querySelector('input[name="license_plate"]')->getAttribute('value'));
        $this->assertSame('true', $page->querySelector('input[name="chassis"]')->getAttribute('aria-invalid'));
        $this->assertNull($page->querySelector('input[name="terms_accepted"][checked]'), 'O aceite não volta marcado.');
    }

    public function test_manual_validation_errors_are_summarised_above_the_form(): void
    {
        $this->actingAs($this->owner)
            ->from(route('user.vehicles.create'))
            ->post(route('user.vehicles.store'), ['license_plate' => 'ABC1D23', 'renavam' => '123'])
            ->assertRedirect(route('user.vehicles.create'));

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.create'))->getContent());

        $summary = $page->querySelector('details[data-vehicle-entry-manual] #manual-erros');
        $this->assertSame('alert', $summary->getAttribute('role'));
        $this->assertNotNull($summary->querySelector('a[href="#renavam"]'));
        $this->assertNotNull($summary->querySelector('a[href="#terms-accepted-checkbox"]'));
        $this->assertTrue($page->querySelector('details[data-vehicle-entry-manual]')->hasAttribute('open'));
    }

    public function test_review_step_lists_vehicle_errors_at_the_top(): void
    {
        $this->actingAs($this->owner)->post(route('user.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);

        $this->actingAs($this->owner)
            ->from(route('user.vehicles.import.preview'))
            ->post(route('user.vehicles.store'), ['crv_number' => '111111111111'] + $this->hondaPayload())
            ->assertRedirect(route('user.vehicles.import.preview'))
            ->assertSessionHasErrors(['vehicle' => 'O número do CRV informado não confere com o CRLV-e.']);

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.import.preview'))->getContent());

        $summary = $page->querySelector('#conferir-erros');
        $this->assertSame('alert', $summary->getAttribute('role'));
        $this->assertStringContainsString('O número do CRV informado não confere com o CRLV-e.', $summary->textContent);
    }

    public function test_existing_vehicle_path_shows_the_history_and_links_it(): void
    {
        // O veículo é de outra conta: o vínculo tira dela, então o CRLV-e precisa estar no CPF da conta.
        $this->owner->update(['document' => self::HONDA_OWNER_DOCUMENT]);
        $vehicle = $this->hondaInRevisaLog();
        Maintenance::factory()->count(2)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($this->owner)
            ->post(route('user.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()])
            ->assertRedirect(route('user.vehicles.claim.preview'))
            ->assertSessionHas('claim_vehicle_id', $vehicle->id);

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.claim.preview'))->assertOk()->getContent());

        $this->assertSingleH1($page, 'Vincular veículo à sua conta');
        $this->assertSame('Veículo já cadastrado', trim($page->querySelector('[data-slot="page-header-eyebrow"]')->textContent));
        $this->assertCurrentStep($page, 2, 3, 'Conferir');

        $history = preg_replace('/\s+/u', ' ', $page->querySelector('[data-claim-history]')->textContent);
        $this->assertStringContainsString('3 manutenções no histórico', $history);
        $this->assertStringContainsString('2 com Selo da oficina', $history);
        $this->assertStringContainsString('1 declarada', $history);

        $form = $page->querySelector('form[data-vehicle-entry-form="claim"]');
        $this->assertSame(route('user.vehicles.claim.store'), $form->getAttribute('action'));
        $this->assertSame('Vincular à minha conta', trim($form->querySelector('button[type="submit"]')->textContent));

        $this->actingAs($this->owner)
            ->post(route('user.vehicles.claim.store'), ['crlv_verification_token' => session('crlv_verification.token')])
            ->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHas('success', 'Veículo vinculado à sua conta.')
            ->assertSessionMissing('claim_vehicle_id');

        $this->assertTrue($this->owner->vehicles()->whereKey($vehicle->id)->wherePivot('is_current_owner', true)->exists());
    }

    public function test_claim_errors_show_on_the_claim_step(): void
    {
        $this->owner->update(['document' => self::HONDA_OWNER_DOCUMENT]);
        $this->hondaInRevisaLog(['crv_number' => '999999999999']);

        $this->actingAs($this->owner)->post(route('user.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);

        $this->actingAs($this->owner)
            ->from(route('user.vehicles.claim.preview'))
            ->post(route('user.vehicles.claim.store'), ['crlv_verification_token' => session('crlv_verification.token')])
            ->assertRedirect(route('user.vehicles.claim.preview'))
            ->assertSessionHasErrors('vehicle');

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.claim.preview'))->getContent());

        $summary = $page->querySelector('#vincular-erros');
        $this->assertSame('alert', $summary->getAttribute('role'));
        $this->assertStringContainsString('Não foi possível vincular o veículo', $summary->textContent);
        $this->assertStringContainsString('O número do CRV do CRLV-e não confere com o veículo cadastrado.', $summary->textContent);
    }

    public function test_expired_claim_reading_goes_back_to_the_document_step(): void
    {
        $this->actingAs($this->owner)
            ->post(route('user.vehicles.claim.store'), ['crlv_verification_token' => 'antigo'])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHasErrors(['crlv' => 'A leitura do CRLV-e expirou. Envie o documento de novo para vincular o veículo.']);

        $this->actingAs($this->owner)->get(route('user.vehicles.claim.preview'))->assertRedirect(route('user.vehicles.create'));
    }

    public function test_claim_step_opens_the_vehicle_when_it_is_already_linked(): void
    {
        $vehicle = $this->hondaInRevisaLog();
        $this->attachVehicleToUser($this->owner, $vehicle);

        $this->actingAs($this->owner)->post(route('user.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);

        $page = $this->page($this->actingAs($this->owner)->get(route('user.vehicles.claim.preview'))->assertOk()->getContent());

        $notice = $page->querySelector('[data-already-linked]');
        $this->assertStringContainsString('Este veículo já está na sua conta', $notice->textContent);
        $this->assertSame(route('user.vehicles.show', $vehicle), $notice->querySelector('a')->getAttribute('href'));
        $this->assertNull($page->querySelector('form[data-vehicle-entry-form="claim"] button[type="submit"]'), 'Sem botão para vincular de novo.');
    }

    /**
     * @return array<string, array{string|null, string, string, string, int, string}>
     */
    public static function garageOwnershipProvider(): array
    {
        return [
            'CRLV-e no CPF/CNPJ da conta' => [self::HONDA_OWNER_DOCUMENT, VehicleEntryFlow::OWNERSHIP_OWNER, 'O CRLV-e está no CPF/CNPJ da sua conta', 'Adicionar ao estoque', 3, 'Capas'],
            'conta sem CNPJ' => [null, VehicleEntryFlow::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT, 'Sua conta não tem o CNPJ da loja', 'Continuar para a procuração', 4, 'Procuração'],
            'CRLV-e de outra pessoa' => ['11.222.333/0001-81', VehicleEntryFlow::OWNERSHIP_OTHER_OWNER, 'CRLV-e em nome de outra pessoa', 'Continuar para a procuração', 4, 'Procuração'],
        ];
    }

    #[DataProvider('garageOwnershipProvider')]
    public function test_garage_review_says_what_will_happen_to_the_ownership(?string $document, string $ownership, string $notice, string $submitLabel, int $totalSteps, string $thirdStep): void
    {
        $this->garage->update(['document' => $document]);

        $this->actingAs($this->garage)->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);

        $page = $this->page($this->actingAs($this->garage)->get(route('garage.vehicles.import.preview'))->assertOk()->getContent());

        $this->assertStringContainsString($notice, $page->querySelector('[data-ownership-notice="'.$ownership.'"]')->textContent);
        $this->assertSame($submitLabel, trim($page->querySelector('button[data-terms-submit]')->textContent));
        $this->assertCurrentStep($page, 2, $totalSteps, 'Conferir');
        $this->assertStringContainsString($thirdStep, $page->querySelectorAll('nav[data-slot="steps"] li')->item(2)->textContent);
        $this->assertStringContainsString('CPF/CNPJ da sua conta', $page->querySelector('[data-crlv-summary]')->textContent);
    }

    public function test_garage_owning_the_crlv_adds_to_stock_and_goes_to_the_covers(): void
    {
        $this->garage->update(['document' => self::HONDA_OWNER_DOCUMENT]);

        $this->actingAs($this->garage)->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);
        $response = $this->actingAs($this->garage)->post(route('garage.vehicles.store'), $this->hondaPayload());
        $vehicle = Vehicle::where('license_plate', 'PHF9J95')->firstOrFail();

        $response->assertRedirect(route('garage.vehicles.covers', $vehicle))
            ->assertSessionHas('success', 'Veículo adicionado ao estoque.');
    }

    public function test_garage_without_document_goes_to_the_power_of_attorney_with_its_own_message(): void
    {
        $this->actingAs($this->garage)->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.store'), $this->hondaPayload())
            ->assertRedirect(route('garage.vehicles.consignment'));

        $pending = session('consignment_pending');
        $this->assertSame(VehicleEntryFlow::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT, $pending['reason']);
        $this->assertArrayNotHasKey('crlv_verification', $pending, 'O CRLV-e lido fica numa cópia só (cookie de 4 KB).');
        $this->assertArrayNotHasKey('terms_accepted', $pending['vehicle_data']);
        $this->assertSame(0, Vehicle::count(), 'Nada é gravado antes da procuração.');

        $page = $this->page($this->actingAs($this->garage)->get(route('garage.vehicles.consignment'))->assertOk()->getContent());

        $this->assertSingleH1($page, 'Veículo em consignação');
        $this->assertSame('Consignação', trim($page->querySelector('[data-slot="page-header-eyebrow"]')->textContent));
        $this->assertCurrentStep($page, 3, 4, 'Procuração');

        $reason = $page->querySelector('[data-consignment-reason="missing_account_document"]');
        $this->assertStringContainsString('Sua conta não tem o CNPJ da loja', $reason->textContent);
        $this->assertSame(route('contact.show'), $reason->querySelector('a')->getAttribute('href'));
        $this->assertNull($page->querySelector('[data-consignment-reason="other_owner"]'));

        $vehicle = preg_replace('/\s+/u', ' ', $page->querySelector('[data-consignment-vehicle]')->textContent);
        $this->assertStringContainsString('Honda Civic · 2016', $vehicle);
        $this->assertStringContainsString('PHF9J95', $vehicle);
        $this->assertStringContainsString('RODRIGO SANCHES DEVIGO · •••.528.458-••', $vehicle);
        $this->assertStringContainsString('Novo: entra junto com a consignação', $vehicle);

        $form = $page->querySelector('form[data-vehicle-entry-form="consignment"]');
        $this->assertSame(route('garage.vehicles.consignment.store'), $form->getAttribute('action'));
        $file = $form->querySelector('input[type="file"][name="power_of_attorney"]');
        $this->assertFalse($file->hasAttribute('required'), 'A procuração é opcional: a declaração já libera o registro de manutenções.');
        $this->assertStringContainsString('application/pdf', $file->getAttribute('accept'));
        $this->assertNotNull($form->querySelector('input[name="consignment_declaration"]'));
        $this->assertNotNull($form->querySelector('input[name="consignment_owner_name"]'));
        $this->assertSame('Salvando…', $form->querySelector('button[type="submit"]:not([form])')->getAttribute('data-loading-label'));

        $cancel = $form->querySelector('button[data-consignment-cancel]');
        $cancelForm = $page->getElementById($cancel->getAttribute('form'));
        $this->assertSame(route('garage.vehicles.consignment.cancel'), $cancelForm->getAttribute('action'));
        $this->assertSame('DELETE', $cancelForm->querySelector('input[name="_method"]')->getAttribute('value'));
    }

    public function test_garage_with_another_document_sees_who_must_sign_the_power_of_attorney(): void
    {
        $this->garage->update(['document' => '11.222.333/0001-81']);
        $vehicle = $this->hondaInRevisaLog();

        $this->actingAs($this->garage)->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.claim.store'), ['crlv_verification_token' => session('crlv_verification.token')])
            ->assertRedirect(route('garage.vehicles.consignment'));

        $this->assertSame(['vehicle_id' => $vehicle->id, 'reason' => VehicleEntryFlow::OWNERSHIP_OTHER_OWNER], session('consignment_pending'));

        $page = $this->page($this->actingAs($this->garage)->get(route('garage.vehicles.consignment'))->assertOk()->getContent());

        $reason = preg_replace('/\s+/u', ' ', $page->querySelector('[data-consignment-reason="other_owner"]')->textContent);
        $this->assertStringContainsString('O proprietário no CRLV-e é RODRIGO SANCHES DEVIGO (•••.528.458-••)', $reason);
        $this->assertStringContainsString('Já cadastrado, com o histórico no chassi', $page->querySelector('[data-consignment-vehicle]')->textContent);
    }

    public function test_declaring_the_consignment_after_the_wizard_adds_the_vehicle_in_consignment(): void
    {
        Storage::fake(AppStorage::diskName());

        $this->actingAs($this->garage)->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);
        $this->actingAs($this->garage)->post(route('garage.vehicles.store'), $this->hondaPayload());

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.store'), [
                'consignment_owner_name' => 'Rodrigo Sanches Devigo',
                'consignment_owner_email' => 'rodrigo@example.com',
                'consignment_declaration' => '1',
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('garage.vehicles.index'))
            ->assertSessionHas('success', 'Veículo em consignação adicionado. Você já pode registrar manutenções; a procuração foi enviada para análise e o histórico anterior abre depois dela.')
            ->assertSessionMissing('consignment_pending')
            ->assertSessionMissing('crlv_verification');

        $vehicle = Vehicle::where('license_plate', 'PHF9J95')->firstOrFail();

        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'ownership_type' => 'consignment',
            'is_current_owner' => false,
        ]);
        $this->assertDatabaseHas('vehicle_consignments', [
            'garage_user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'owner_name' => 'Rodrigo Sanches Devigo',
            'owner_email' => 'rodrigo@example.com',
            'status' => 'active',
            'history_access_status' => 'pending',
        ]);
    }

    public function test_consignment_needs_the_declaration_and_a_contact_for_the_owner(): void
    {
        $this->actingAs($this->garage)->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);
        $this->actingAs($this->garage)->post(route('garage.vehicles.store'), $this->hondaPayload());

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.store'), ['consignment_owner_name' => 'Rodrigo Sanches Devigo'])
            ->assertSessionHasErrors([
                'consignment_declaration' => 'Confirme que você tem autorização do proprietário para registrar manutenções.',
                'consignment_owner_email' => 'Informe e-mail ou telefone do proprietário para podermos avisá-lo.',
            ]);

        $this->assertDatabaseCount('vehicle_consignments', 0);
    }

    public function test_power_of_attorney_must_be_a_pdf_up_to_ten_megabytes(): void
    {
        $this->actingAs($this->garage)
            ->withSession(['consignment_pending' => ['vehicle_id' => 1]])
            ->post(route('garage.vehicles.consignment.store'), $this->declarationPayload([
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.jpg', 120, 'image/jpeg'),
            ]))
            ->assertSessionHasErrors(['power_of_attorney' => 'A procuração precisa ser um arquivo PDF.']);

        $this->actingAs($this->garage)
            ->withSession(['consignment_pending' => ['vehicle_id' => 1]])
            ->post(route('garage.vehicles.consignment.store'), $this->declarationPayload([
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 11 * 1024, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['power_of_attorney' => 'A procuração pode ter no máximo 10 MB.']);
    }

    public function test_expired_reading_on_the_power_of_attorney_step_restarts_the_wizard(): void
    {
        Storage::fake(AppStorage::diskName());

        $this->actingAs($this->garage)
            ->withSession(['consignment_pending' => ['vehicle_id' => 1]])
            ->post(route('garage.vehicles.consignment.store'), $this->declarationPayload([
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect(route('garage.vehicles.create'))
            ->assertSessionHasErrors(['crlv' => 'A leitura do CRLV-e expirou. Envie o documento de novo.'])
            ->assertSessionMissing('consignment_pending');
    }

    #[DataProvider('portalProvider')]
    public function test_cancel_on_the_power_of_attorney_step_discards_the_reading(string $actor, string $prefix): void
    {
        $flow = $this->flowFor($prefix);

        $this->actingAs($this->{$actor})
            ->withSession([
                'consignment_pending' => ['vehicle_data' => ['license_plate' => 'PHF9J95'], 'reason' => VehicleEntryFlow::OWNERSHIP_OTHER_OWNER],
                'crlv_verification' => ['token' => 'x', 'parsed' => ['license_plate' => 'PHF9J95']],
                'crlv_source' => 'CRLV-e.pdf',
            ])
            ->delete($flow->url('consignment.cancel'))
            ->assertRedirect($flow->url('create'))
            ->assertSessionHas('info', $flow->consignmentCancelledMessage())
            ->assertSessionMissing('consignment_pending')
            ->assertSessionMissing('crlv_verification')
            ->assertSessionMissing('crlv_source');

        $this->assertSame(0, Vehicle::count());
    }

    #[DataProvider('portalProvider')]
    public function test_power_of_attorney_step_without_a_pending_consignment_goes_to_the_list(string $actor, string $prefix): void
    {
        $flow = $this->flowFor($prefix);

        $this->actingAs($this->{$actor})->get($flow->url('consignment'))->assertRedirect($flow->listUrl());
    }

    public function test_owner_power_of_attorney_step_uses_owner_copy(): void
    {
        $page = $this->page($this->actingAs($this->owner)
            ->withSession(['consignment_pending' => ['vehicle_data' => ['license_plate' => 'PHF9J95', 'brand' => 'Honda', 'model' => 'Civic', 'year' => 2016]]])
            ->get(route('user.vehicles.consignment'))
            ->assertOk()
            ->getContent());

        $this->assertSingleH1($page, 'Veículo em consignação');
        $this->assertSame('Documento em nome de outra pessoa', trim($page->querySelector('[data-slot="page-header-eyebrow"]')->textContent));
        $this->assertStringNotContainsString('estoque', $page->querySelector('[data-consignment-steps]')->textContent);
        $this->assertSame(route('user.vehicles.consignment.cancel'), $page->querySelector('form[method="POST"][hidden]')->getAttribute('action'));
    }

    public function test_wizard_pages_are_not_open_to_the_other_portal(): void
    {
        $this->actingAs($this->owner)->get(route('garage.vehicles.create'))->assertRedirect();
        $this->actingAs($this->garage)->get(route('user.vehicles.create'))->assertRedirect();
        $this->actingAs($this->garage)->get(route('user.vehicles.consignment'))->assertRedirect(route('garage.dashboard'));
    }

    public function test_document_step_warns_a_garage_without_document(): void
    {
        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.create'))
            ->assertOk()
            ->assertSee('data-account-document-missing', false)
            ->assertSee('Sua conta ainda não tem o CNPJ da loja');

        $this->garage->update(['document' => self::HONDA_OWNER_DOCUMENT]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.create'))
            ->assertOk()
            ->assertDontSee('data-account-document-missing', false);

        $this->actingAs($this->owner)
            ->get(route('user.vehicles.create'))
            ->assertOk()
            ->assertDontSee('data-account-document-missing', false);
    }

    public function test_document_mask_keeps_only_part_of_cpf_and_cnpj(): void
    {
        $this->assertSame('•••.528.458-••', DocumentMask::cpfOrCnpj('374.528.458-54'));
        $this->assertSame('11.222.333/••••-••', DocumentMask::cpfOrCnpj('11222333000181'));
        $this->assertSame('•••45', DocumentMask::cpfOrCnpj('12345'));
        $this->assertNull(DocumentMask::cpfOrCnpj(null));
        $this->assertNull(DocumentMask::cpfOrCnpj('abc'));
    }

    public function test_the_flow_exists_only_for_owner_and_dealer(): void
    {
        $this->assertSame('user.vehicles.create', VehicleEntryFlow::for(Portal::Owner)->routeName('create'));
        $this->assertSame('garage.vehicles.covers', VehicleEntryFlow::for(Portal::Dealer)->routeName('covers'));
        $this->assertSame(Portal::Owner->primaryAction()['label'], VehicleEntryFlow::for(Portal::Owner)->title());
        $this->assertSame(Portal::Dealer->primaryAction()['label'], VehicleEntryFlow::for(Portal::Dealer)->title());

        $this->expectException(\InvalidArgumentException::class);
        VehicleEntryFlow::for(Portal::Workshop);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function declarationPayload(array $overrides = []): array
    {
        return array_merge([
            'consignment_owner_name' => 'Rodrigo Sanches Devigo',
            'consignment_owner_email' => 'rodrigo@example.com',
            'consignment_declaration' => '1',
        ], $overrides);
    }

    private function flowFor(string $prefix): VehicleEntryFlow
    {
        return VehicleEntryFlow::for($prefix === 'garage' ? Portal::Dealer : Portal::Owner);
    }

    private function page(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    private function assertSingleH1(HTMLDocument $page, string $title): void
    {
        $headings = $page->querySelectorAll('h1');

        $this->assertSame(1, $headings->length, 'Uma página, um H1.');
        $this->assertSame($title, trim($headings->item(0)->textContent));
        $this->assertStringStartsWith($title.' · ', trim($page->querySelector('title')->textContent), 'O <title> repete o H1.');
    }

    private function assertCurrentStep(HTMLDocument $page, int $number, int $total, string $label): void
    {
        $steps = $page->querySelectorAll('nav[data-slot="steps"] li');
        $this->assertSame($total, $steps->length);

        $current = $page->querySelectorAll('nav[data-slot="steps"] li[aria-current="step"]');
        $this->assertSame(1, $current->length);
        $this->assertInstanceOf(Element::class, $current->item(0));
        $this->assertStringContainsString("Etapa {$number} de {$total}: {$label}", preg_replace('/\s+/u', ' ', $current->item(0)->textContent));
    }

    private function crlvUpload(string $fixture = 'honda_civic_ms.pdf'): UploadedFile
    {
        return new UploadedFile(base_path('tests/fixtures/crlv/'.$fixture), 'CRLV-e.pdf', 'application/pdf', null, true);
    }

    /**
     * Campos do formulário Conferir para o CRLV-e honda_civic_ms.pdf.
     *
     * @return array<string, mixed>
     */
    private function hondaPayload(): array
    {
        return [
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
            'current_kilometers' => 85_000,
            'terms_accepted' => '1',
            'crlv_verification_token' => session('crlv_verification.token'),
        ];
    }

    /**
     * O Honda do CRLV-e de teste já cadastrado na RevisaLog, por outra pessoa.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function hondaInRevisaLog(array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::factory()->create($attributes + [
            'brand' => 'Honda',
            'model' => 'Civic',
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'chassis' => '93HFB9640GZ202125',
        ]);

        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicleOwnedBy(User $user, array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::factory()->create($attributes);
        $this->attachVehicleToUser($user, $vehicle);

        return $vehicle;
    }
}
