<?php

namespace App\Services\Maintenance;

use App\Exceptions\SelfAttestationException;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Workshop\WorkshopLeadRecorder;
use App\Support\Maintenance\VerificationCode;
use App\Support\Vehicle\IssuerIndependence;
use Illuminate\Support\Facades\DB;

class MaintenanceVerificationStamper
{
    public function stamp(Maintenance $maintenance, User $actor): Maintenance
    {
        if ($maintenance->verified_at !== null) {
            return $maintenance;
        }

        // Oficina selando a OS que registrou — o caminho principal. Quando o veículo é do tenant
        // dela, o selo não sai e o registro cai na procedência declarada abaixo: o serviço existiu e
        // o histórico pertence ao chassi (.ai/rules/models.md), mas quem atesta é a própria dona, e
        // é isso que "Declarada" diz com honestidade. Recusar a OS inteira apagaria histórico real.
        if ($actor->isWorkshop() && $actor->workshop && $maintenance->workshop_id === $actor->workshop->id) {
            if ($this->mayAttest($maintenance, (int) $actor->workshop->id)) {
                return $this->applyWorkshopSeal($maintenance, $actor->workshop->id);
            }
        } elseif (self::autoVerifyEnabled() && $maintenance->workshop_id !== null) {
            if ($this->mayAttest($maintenance, (int) $maintenance->workshop_id)) {
                return $this->applyWorkshopSeal($maintenance, (int) $maintenance->workshop_id);
            }
        }

        $type = match (true) {
            $actor->isGarage() => 'garage',
            default => 'owner',
        };

        $maintenance->forceFill([
            'registered_by_type' => $type,
            'verified_at' => null,
            'verified_workshop_id' => null,
            'verification_code' => null,
        ])->save();

        $maintenance = $maintenance->fresh();
        app(WorkshopReviewService::class)->syncAfterDeclaration($maintenance);
        app(WorkshopLeadRecorder::class)->record($maintenance);

        return $maintenance->fresh();
    }

    /**
     * Selo de uma OS de oficina que, por algum motivo, nasceu sem ele (dados de um período em que o
     * selo dependia de vínculo provado com o veículo). Usado quando o proprietário vincula o
     * registro ao histórico dele: não há mais caminho que crie OS de oficina sem selo.
     */
    public function sealAfterOwnerLink(Maintenance $maintenance, int $workshopId): Maintenance
    {
        if ($maintenance->verified_at !== null) {
            return $maintenance;
        }

        // O dono que acabou de vincular é do mesmo tenant da oficina: vincular segue valendo (o
        // registro entra no histórico dele), o selo é que não nasce retroativo. Devolver sem selo em
        // vez de recusar mantém a decisão do proprietário, que é o que a tela dele promete.
        if (! $this->mayAttest($maintenance, $workshopId)) {
            return $maintenance;
        }

        return $this->applyWorkshopSeal($maintenance, $workshopId, 'confirmed');
    }

    /**
     * Selo para uma declarada que a oficina citada confirmou (WorkshopReviewService::confirm). O
     * registro continua com o tenant e o autor de quem declarou; só a procedência muda.
     */
    public function confirmDeclared(Maintenance $maintenance, int $workshopId): Maintenance
    {
        return $this->applyWorkshopSeal($maintenance, $workshopId, 'confirmed');
    }

    /**
     * A oficina pode atestar sobre o veículo desta OS? A relação é carregada com `loadMissing`
     * porque o `preventLazyLoading` fora de produção derrubaria o acesso direto — e aqui o certo é
     * responder, não quebrar.
     */
    private function mayAttest(Maintenance $maintenance, int $workshopId): bool
    {
        $maintenance->loadMissing('vehicle');
        $vehicle = $maintenance->getRelationValue('vehicle');

        return IssuerIndependence::workshopMayAttest(
            $workshopId,
            $vehicle instanceof Vehicle ? $vehicle : null,
        );
    }

    /**
     * O atalho de QA que dá o selo a toda declarada que cita uma oficina nunca vale em produção:
     * ali o selo só nasce da OS registrada ou confirmada pela própria oficina.
     */
    public static function autoVerifyEnabled(): bool
    {
        return (bool) config('maintenance.auto_verify_linked_workshop') && ! app()->isProduction();
    }

    public function verifyLinkedWorkshopMaintenances(): int
    {
        $count = 0;

        Maintenance::query()
            ->whereNotNull('workshop_id')
            ->whereNull('verified_at')
            ->orderBy('id')
            ->each(function (Maintenance $maintenance) use (&$count): void {
                // Backfill pula o que não pode selar em vez de morrer na linha: uma OS conflitada no
                // meio da tabela não pode travar as outras.
                if (! $this->mayAttest($maintenance, (int) $maintenance->workshop_id)) {
                    return;
                }

                $this->applyWorkshopSeal($maintenance, (int) $maintenance->workshop_id);
                $count++;
            });

        return $count;
    }

    /**
     * O gargalo único do selo: todo caminho de procedência verificada passa por aqui, então é aqui
     * que o invariante de conflito de interesse é inegociável. Os chamadores perguntam antes com
     * `mayAttest` e tratam a recusa com o que faz sentido em cada um (declarada, selo não aplicado,
     * 422 na fila de validação); esta asserção existe para o caminho que **esquecer** de perguntar —
     * incluindo o próximo. Um selo que mente é pior que uma exceção.
     *
     * @throws SelfAttestationException
     */
    private function applyWorkshopSeal(Maintenance $maintenance, int $workshopId, string $method = 'registered'): Maintenance
    {
        if (! $this->mayAttest($maintenance, $workshopId)) {
            throw SelfAttestationException::forVehicle($workshopId, $maintenance->vehicle_id);
        }

        $maintenance->forceFill([
            'registered_by_type' => 'workshop',
            'verified_at' => now(),
            'verified_workshop_id' => $workshopId,
            'verification_code' => $this->generateUniqueCode(),
            'verification_method' => $method,
            'workshop_id' => $workshopId,
        ])->save();

        return $maintenance->fresh();
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = VerificationCode::random();

            if (! DB::table('maintenances')->where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar código de verificação único.');
    }
}
