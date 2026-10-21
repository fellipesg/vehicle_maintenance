<?php

namespace Tests\Feature\Web;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Rules\InvoiceFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * Registrar e editar OS no portal do Proprietário e do Lojista recebe nota fiscal, e a App\Rules\InvoiceFile
 * lê o arquivo inteiro (MIME por conteúdo, cabeçalho do PDF e uma passada de XMLReader). Por isso essas
 * rotas entram no mesmo balde 'uploads' das rotas da oficina e da API (10/min por conta,
 * App\Providers\AppServiceProvider) e o array `invoices` tem teto de InvoiceFile::MAX_FILES_PER_REQUEST
 * notas por requisição.
 */
class MaintenanceInvoiceUploadLimitsTest extends TestCase
{
    use InspectsGaragePages;
    use InspectsOwnerPages;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function invoiceRouteProvider(): array
    {
        return [
            'nova OS (proprietário)' => ['owner-store'],
            'editar OS (proprietário)' => ['owner-update'],
            'nova OS (lojista)' => ['dealer-store'],
        ];
    }

    #[DataProvider('invoiceRouteProvider')]
    public function test_every_owner_and_dealer_invoice_route_is_throttled(string $surface): void
    {
        [$actor, $method, $url] = $this->invoiceRoute($surface);

        // Payload incompleto de propósito: o balde conta a requisição antes do controller, então o
        // limite fecha a rota mesmo para quem só esbarra na validação.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($actor)->$method($url, []);
        }

        $this->actingAs($actor)->$method($url, [])->assertStatus(429);
    }

    /**
     * O balde é o mesmo das outras rotas de upload da conta: gastar a cota no CRLV-e fecha o
     * cadastro de OS, e vice-versa.
     */
    public function test_the_invoice_routes_share_the_bucket_with_the_other_uploads(): void
    {
        $owner = $this->owner();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($owner)->post(route('user.maintenances.store'), []);
        }

        $this->actingAs($owner)
            ->post(route('user.vehicles.import-crlv'), [])
            ->assertStatus(429);
    }

    #[DataProvider('invoiceRouteProvider')]
    public function test_more_invoices_than_the_limit_are_refused(string $surface): void
    {
        Storage::fake('public');

        [$actor, $method, $url, $payload] = $this->invoiceRoute($surface);

        $this->actingAs($actor)
            ->$method($url, $payload + ['invoices' => $this->invoicePdfs(InvoiceFile::MAX_FILES_PER_REQUEST + 1)])
            ->assertSessionHasErrors([
                'invoices' => 'Envie no máximo '.InvoiceFile::MAX_FILES_PER_REQUEST.' notas fiscais por vez. Se precisar anexar mais, salve e envie o resto depois.',
            ]);

        $this->assertSame(0, Invoice::count(), 'Nenhuma nota é gravada quando o lote passa do teto.');
    }

    #[DataProvider('invoiceRouteProvider')]
    public function test_the_limit_itself_still_goes_through(string $surface): void
    {
        Storage::fake('public');

        [$actor, $method, $url, $payload] = $this->invoiceRoute($surface);

        $this->actingAs($actor)
            ->$method($url, $payload + ['invoices' => $this->invoicePdfs(InvoiceFile::MAX_FILES_PER_REQUEST)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(InvoiceFile::MAX_FILES_PER_REQUEST, Invoice::count());
    }

    /**
     * O teto é o mesmo na API: o app manda `invoices` no POST /api/v1/maintenances e no PUT, e a
     * mensagem sai em pt-BR no envelope.
     */
    public function test_the_api_uses_the_same_limit(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser();
        $vehicle = $this->ownedVehicle($user, ['current_kilometers' => 50_000, 'odometer_at_registration' => 50_000]);
        $payload = [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 60_000,
            'service_category' => 'mechanical',
        ];

        $this->post('/api/v1/maintenances', $payload + ['invoices' => $this->invoicePdfs(InvoiceFile::MAX_FILES_PER_REQUEST + 1)])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.invoices.0',
                'Envie no máximo '.InvoiceFile::MAX_FILES_PER_REQUEST.' notas fiscais por vez. Se precisar anexar mais, salve e envie o resto depois.',
            );

        $this->assertSame(0, Invoice::count());

        $this->post('/api/v1/maintenances', $payload + ['invoices' => $this->invoicePdfs(InvoiceFile::MAX_FILES_PER_REQUEST)])
            ->assertCreated();

        $this->assertSame(InvoiceFile::MAX_FILES_PER_REQUEST, Invoice::count());
    }

    /**
     * @return list<UploadedFile>
     */
    private function invoicePdfs(int $count): array
    {
        return array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->create("nota-{$index}.pdf", 10, 'application/pdf'),
            range(1, $count),
        );
    }

    /**
     * Conta, método, URL e o payload mínimo aceito por cada superfície que recebe `invoices`.
     *
     * @return array{User, string, string, array<string, mixed>}
     */
    private function invoiceRoute(string $surface): array
    {
        if ($surface === 'dealer-store') {
            $dealer = User::factory()->asGarage()->create();
            $vehicle = $this->stockVehicle($dealer, ['current_kilometers' => 30_000, 'odometer_at_registration' => 30_000]);

            return [$dealer, 'post', route('garage.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão pré-venda',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 30_500,
                'service_category' => 'mechanical',
            ]];
        }

        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 50_000, 'odometer_at_registration' => 50_000]);
        $payload = [
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 60_000,
            'service_category' => 'mechanical',
        ];

        if ($surface === 'owner-update') {
            $maintenance = Maintenance::factory()->declaredByOwner()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 60_000,
            ]);

            return [$owner, 'put', route('user.maintenances.update', $maintenance), $payload];
        }

        return [$owner, 'post', route('user.maintenances.store'), $payload + ['vehicle_id' => $vehicle->id]];
    }
}
