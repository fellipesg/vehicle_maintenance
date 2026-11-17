<?php

namespace App\Http\Requests\Concerns;

use App\Enums\InspectionFindingStatus;
use App\Enums\InspectionVerdict;
use App\Models\VehicleInspection;
use App\Support\Inspection\InspectionFindings;
use Illuminate\Validation\Rule;

/**
 * As regras do laudo que o store e o update têm em comum (StoreVehicleInspectionRequest,
 * UpdateVehicleInspectionRequest).
 *
 * É aqui que mora `before_or_equal:today` da data da vistoria, como nas quatro superfícies de OS: a
 * data é do formulário, não do serviço. O que o serviço guarda são as duas garantias do laudo —
 * veredito contra eixo reprovado e hodômetro contra o histórico —, que não são formato e por isso
 * não cabem numa regra de validação (App\Services\Inspection\VehicleInspectionService).
 */
trait ValidatesVehicleInspection
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function inspectionRules(): array
    {
        return [
            'inspected_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'verdict' => ['required', Rule::in(InspectionVerdict::values())],
            'valid_until' => ['nullable', 'date', 'after_or_equal:inspected_at'],
            'summary' => ['nullable', 'string', 'max:'.VehicleInspection::MAX_SUMMARY_LENGTH],
            'findings' => ['nullable', 'array'],
            'findings.*.status' => ['nullable', Rule::in(InspectionFindingStatus::values())],
            'findings.*.note' => ['nullable', 'string', 'max:'.InspectionFindings::MAX_NOTE_LENGTH],
            'report' => ['nullable', 'file', 'mimes:pdf', 'max:'.VehicleInspection::MAX_REPORT_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function inspectionMessages(): array
    {
        return [
            'inspected_at.required' => 'Informe a data da vistoria.',
            'inspected_at.before_or_equal' => 'A data da vistoria não pode ser no futuro.',
            'verdict.required' => 'Escolha o resultado do laudo.',
            'verdict.in' => 'Escolha o resultado do laudo.',
            'valid_until.after_or_equal' => 'A validade não pode ser anterior à data da vistoria.',
            'report.mimes' => 'O laudo deve ser um arquivo PDF.',
            'findings.*.note.max' => 'A observação de cada item aceita até '.InspectionFindings::MAX_NOTE_LENGTH.' caracteres.',
        ];
    }
}
