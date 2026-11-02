<?php

namespace Tests\Unit;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Notifications\MaintenanceDisputedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O aviso que a oficina recebe quando o proprietário contesta uma OS dela.
 *
 * O que este teste guarda é a veracidade do texto. A contestação **marca** o registro e não o
 * esconde (`.ai/rules/public-lookup.md`), mas este e-mail afirmava que a OS saía da consulta
 * pública e emendava com "corrija ou exclua a OS". A oficina autora pode excluir a própria OS
 * mesmo depois do selo (MaintenancePolicy::canChange), então o texto antigo levava quem tem NF-e e
 * serviço feito a apagar um registro legítimo — a remoção unilateral que o fluxo recusa dar ao
 * proprietário, obtida por engano pelo outro lado.
 */
class MaintenanceDisputedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_says_the_record_stays_public_and_keeps_the_seal(): void
    {
        $body = $this->mailBody();

        $this->assertStringContainsString('continua na consulta pública', $body);
        $this->assertStringContainsString('Selo da oficina não é revogado', $body);
        $this->assertStringContainsString('deixa de contar para o hodômetro', $body);
    }

    public function test_mail_never_claims_the_record_was_suppressed(): void
    {
        $body = $this->mailBody();

        $this->assertStringNotContainsString('não aparece na consulta pública', $body);
        $this->assertStringNotContainsString('nem no histórico de terceiros', $body);
    }

    /**
     * O serviço que aconteceu de verdade não se corrige nem se exclui: é o caso em que a oficina
     * tem a NF-e e a OS, e o pedido do cliente é só não constar.
     */
    public function test_mail_separates_a_typo_from_a_service_that_happened(): void
    {
        $body = $this->mailBody();

        $this->assertStringContainsString('Se foi engano de digitação', $body);
        $this->assertStringContainsString('Se o serviço foi feito mesmo, não altere nada', $body);
        $this->assertStringContainsString('guarde a nota fiscal', $body);
    }

    public function test_mail_carries_the_reason_and_the_brand_salutation(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create(['name' => 'Ana Oficina']);
        $mail = (new MaintenanceDisputedNotification($this->record(), 'Chassi errado.'))->toMail($workshopUser);

        $this->assertSame('RevisaLog', $mail->salutation);
        $this->assertStringContainsString(
            'O que o proprietário informou: Chassi errado.',
            implode(' ', array_merge($mail->introLines, $mail->outroLines)),
        );
    }

    private function mailBody(): string
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $mail = (new MaintenanceDisputedNotification($this->record()))->toMail($workshopUser);

        return implode(' ', array_merge($mail->introLines, $mail->outroLines));
    }

    private function record(): Maintenance
    {
        $workshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create();

        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'registered_by_type' => 'workshop',
            'owner_disputed_at' => now(),
        ]);
    }
}
