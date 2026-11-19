<?php

namespace App\Services\Inspection;

use App\Enums\InspectionFinding;
use App\Enums\InspectionVerdict;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Services\Maintenance\OwnerlessMaintenanceService;
use App\Services\Vehicle\VehicleMileageService;
use App\Support\AppStorage;
use App\Support\Inspection\InspectionFindings;
use App\Support\Maintenance\VerificationCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A única escrita de veredito, achados e código público de um laudo. Formulário e API montam o array
 * e chamam daqui; `verdict`, `findings` e `verification_code` não são fillable justamente para que
 * nenhum `update()` de controller contorne as duas garantias abaixo.
 *
 * As duas são assimétricas de propósito: recusam a afirmação que **engana quem compra**, e deixam
 * passar a que só prejudica quem vende. "Aprovado" escondendo um eixo reprovado é a primeira;
 * "aprovado com restrições" sem restrição nenhuma é a segunda, e o vistoriador assina o que quiser.
 *
 * As duas também guardam a **transição, não o estado**: laudo cuja gravação não mexeu em veredito,
 * achados nem hodômetro passa sem ser reavaliado, senão um laudo antigo (ou um que mudou de regra
 * depois) trancaria a própria tela de edição. É o mesmo padrão de
 * EnforcesWorkshopMessageTemplateLimit e de App\Rules\KnownMessageTemplateTags.
 */
class VehicleInspectionService
{
    /**
     * Quantas vezes tentar um código antes de desistir (colisão é improvável; ficar em silêncio
     * não é opção).
     */
    private const CODE_ATTEMPTS = 30;

    /**
     * Extensões aceitas para o laudo. O nome que o cliente envia nunca entra no caminho (LGPD: pode
     * trazer nome e CPF), como em .ai/rules/models.md.
     */
    private const ALLOWED_REPORT_EXTENSIONS = ['pdf'];

    private const REPORT_PREFIX = 'inspections/';

    public function __construct(
        private readonly VehicleMileageService $mileage,
        private readonly OwnerlessMaintenanceService $ownerless,
    ) {}

    /**
     * @param  array{inspected_at: mixed, odometer_km?: mixed, verdict: mixed, findings?: mixed, summary?: mixed, valid_until?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function create(Vehicle $vehicle, Workshop $workshop, User $actor, array $data): VehicleInspection
    {
        $verdict = $this->verdictFrom($data['verdict'] ?? null);
        $findings = InspectionFindings::normalize($data['findings'] ?? []);
        $odometer = $this->odometerFrom($data['odometer_km'] ?? null);

        $this->assertCoherent($vehicle, $verdict, $findings, $odometer, null);

        $inspection = new VehicleInspection([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $this->ownerless->tenantIdFor($vehicle),
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'user_id' => $actor->id,
            'inspected_at' => $data['inspected_at'],
            'odometer_km' => $odometer,
            'summary' => $this->summaryFrom($data['summary'] ?? null),
            'valid_until' => $data['valid_until'] ?? null,
        ]);

        $inspection->forceFill([
            'verdict' => $verdict,
            'findings' => $findings,
            'verification_code' => $this->generateUniqueCode(),
        ])->save();

        return $inspection->fresh();
    }

    /**
     * @param  array{inspected_at?: mixed, odometer_km?: mixed, verdict?: mixed, findings?: mixed, summary?: mixed, valid_until?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function update(VehicleInspection $inspection, array $data): VehicleInspection
    {
        $verdict = array_key_exists('verdict', $data)
            ? $this->verdictFrom($data['verdict'])
            : $this->verdictFrom($inspection->verdict);

        $findings = array_key_exists('findings', $data)
            ? InspectionFindings::normalize($data['findings'])
            : InspectionFindings::normalize($inspection->findings);

        $odometer = array_key_exists('odometer_km', $data)
            ? $this->odometerFrom($data['odometer_km'])
            : $this->odometerFrom($inspection->odometer_km);

        $inspection->loadMissing('vehicle');
        $vehicle = $inspection->getRelationValue('vehicle');

        if (! $vehicle instanceof Vehicle) {
            throw new \RuntimeException('Laudo sem veículo: não há com o que conferir a leitura do hodômetro.');
        }

        $this->assertCoherent($vehicle, $verdict, $findings, $odometer, $inspection);

        $fill = [];

        foreach (['inspected_at', 'valid_until'] as $key) {
            if (array_key_exists($key, $data)) {
                $fill[$key] = $data[$key];
            }
        }

        if (array_key_exists('summary', $data)) {
            $fill['summary'] = $this->summaryFrom($data['summary']);
        }

        $inspection->fill($fill);

        $inspection->forceFill([
            'verdict' => $verdict,
            'findings' => $findings,
            'odometer_km' => $odometer,
        ])->save();

        return $inspection->fresh();
    }

    /**
     * Grava o laudo em PDF sob nome aleatório e troca o anterior. O arquivo antigo é apagado: é
     * substituição pedida pelo emissor, não limpeza automática.
     */
    public function attachReport(VehicleInspection $inspection, UploadedFile $file): VehicleInspection
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_REPORT_EXTENSIONS, true)) {
            $extension = strtolower((string) $file->guessExtension());
        }

        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        if (! in_array($extension, self::ALLOWED_REPORT_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'report' => 'O laudo deve ser um arquivo PDF.',
            ]);
        }

        $fileName = Str::random(40).'.'.$extension;
        $path = $file->storeAs(rtrim(self::REPORT_PREFIX, '/'), $fileName, AppStorage::diskName());

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'report' => 'Não foi possível guardar o laudo. Tente novamente.',
            ]);
        }

        $previous = $inspection->report_path;

        $inspection->forceFill([
            'report_path' => $path,
            'report_original_name' => $file->getClientOriginalName(),
        ])->save();

        $this->deleteReportFile($previous);

        return $inspection->fresh();
    }

    public function detachReport(VehicleInspection $inspection): VehicleInspection
    {
        $previous = $inspection->report_path;

        $inspection->forceFill([
            'report_path' => null,
            'report_original_name' => null,
        ])->save();

        $this->deleteReportFile($previous);

        return $inspection->fresh();
    }

    /**
     * As guardas rodam por campo que mudou, não por gravação. Reavaliar tudo a cada `save` faria um
     * laudo gravado antes (ou sob outra regra) trancar a própria tela de edição: a oficina não
     * conseguiria nem corrigir a data sem antes "arrumar" um hodômetro que não está mexendo.
     *
     * @param  array<string, array{status: string, note: string|null}>  $findings
     *
     * @throws ValidationException
     */
    private function assertCoherent(
        Vehicle $vehicle,
        InspectionVerdict $verdict,
        array $findings,
        ?int $odometerKm,
        ?VehicleInspection $existing,
    ): void {
        $claimChanged = $existing === null
            || $existing->verdict !== $verdict
            || InspectionFindings::normalize($existing->findings) != $findings;

        $readingChanged = $existing === null
            || $this->odometerFrom($existing->odometer_km) !== $odometerKm;

        if ($claimChanged) {
            $this->assertVerdictMatchesFindings($verdict, $findings);
        }

        if ($readingChanged && $odometerKm !== null) {
            $this->mileage->assertInspectionKilometers($vehicle, $odometerKm);
        }

        // Depende dos dois: o eixo do hodômetro e o número que ele comenta.
        if ($claimChanged || $readingChanged) {
            $this->assertOdometerFindingMatchesReading($vehicle, $findings, $odometerKm);
        }
    }

    /**
     * "Aprovado" não convive com eixo reprovado. É a única afirmação do laudo que esconde a
     * reprovação de quem compra o carro.
     *
     * @param  array<string, array{status: string, note: string|null}>  $findings
     *
     * @throws ValidationException
     */
    private function assertVerdictMatchesFindings(InspectionVerdict $verdict, array $findings): void
    {
        if ($verdict->toleratesFailedFindings()) {
            return;
        }

        $failing = InspectionFindings::failing($findings);

        if ($failing === []) {
            return;
        }

        $labels = implode(', ', array_map(fn (InspectionFinding $axis): string => $axis->label(), $failing));

        throw ValidationException::withMessages([
            'verdict' => count($failing) === 1
                ? "O laudo reprova {$labels}, então não pode ser \"Aprovado\". Use \"Aprovado com restrições\" ou \"Reprovado\"."
                : "O laudo reprova estes itens: {$labels}. Então não pode ser \"Aprovado\": use \"Aprovado com restrições\" ou \"Reprovado\".",
        ]);
    }

    /**
     * Leitura de hodômetro abaixo de uma OS que conta para o hodômetro é o indício de adulteração
     * que a cautelar existe para encontrar. A leitura é aceita — é justamente o achado —, mas o
     * eixo do hodômetro não pode dizer "sem apontamento" contra o próprio número do laudo.
     *
     * @param  array<string, array{status: string, note: string|null}>  $findings
     *
     * @throws ValidationException
     */
    private function assertOdometerFindingMatchesReading(Vehicle $vehicle, array $findings, ?int $odometerKm): void
    {
        if ($odometerKm === null) {
            return;
        }

        if (! InspectionFindings::statusOf($findings, InspectionFinding::Odometer)->assertsNothingWrong()) {
            return;
        }

        $above = $this->mileage->countedMaintenanceAbove($vehicle, $odometerKm);

        if ($above === null) {
            return;
        }

        $date = $above->maintenance_date?->format('d/m/Y');
        $reference = $date === null
            ? "{$above->kilometers} km já registrados"
            : "{$above->kilometers} km registrados em {$date}";

        throw ValidationException::withMessages([
            'findings.odometer.status' => "A leitura de {$odometerKm} km está abaixo de {$reference}. O hodômetro não pode ficar como \"Sem apontamento\": aponte \"Ponto de atenção\" ou \"Reprovado\".",
        ]);
    }

    private function verdictFrom(mixed $verdict): InspectionVerdict
    {
        if ($verdict instanceof InspectionVerdict) {
            return $verdict;
        }

        $resolved = is_string($verdict) ? InspectionVerdict::tryFrom($verdict) : null;

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'verdict' => 'Escolha o resultado do laudo.',
            ]);
        }

        return $resolved;
    }

    private function odometerFrom(mixed $odometer): ?int
    {
        if ($odometer === null || $odometer === '') {
            return null;
        }

        return (int) $odometer;
    }

    private function summaryFrom(mixed $summary): ?string
    {
        if (! is_string($summary)) {
            return null;
        }

        $summary = trim($summary);

        return $summary === '' ? null : $summary;
    }

    private function deleteReportFile(?string $path): void
    {
        if (! is_string($path) || $path === '' || ! str_starts_with($path, self::REPORT_PREFIX)) {
            return;
        }

        try {
            AppStorage::diskForPath($path)->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < self::CODE_ATTEMPTS; $attempt++) {
            $code = VerificationCode::random(VerificationCode::INSPECTION_PREFIX);

            if (! DB::table('vehicle_inspections')->where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar código único para o laudo.');
    }
}
