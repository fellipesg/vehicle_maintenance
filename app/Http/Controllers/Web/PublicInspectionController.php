<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VehicleInspection;
use App\Support\Inspection\InspectionRedactor;
use App\Support\Maintenance\VerificationCode;
use App\Support\Vehicle\VehicleIdentifierMask;
use App\Support\VerificationQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Conferência pública do laudo de vistoria cautelar: `GET /laudo/{código}`, sem login, atrás de
 * `throttle:search` como `/v/{código}` (.ai/rules/routes.md). É a página que o comprador abre com o
 * carro na frente, pelo QR ou digitando o código no mesmo campo do Selo (`/verificar`).
 *
 * Quem não tem o histórico daquele veículo recebe a forma mínima de
 * App\Support\Inspection\InspectionRedactor — sem resumo, sem as observações de cada eixo e sem o
 * PDF, mas **com** veredito, data, hodômetro lido, validade, emissor e o status dos oito eixos. A
 * direção é a mesma das outras superfícies públicas: quem compra fica com mais informação, não
 * menos (.ai/rules/public-lookup.md).
 */
class PublicInspectionController extends Controller
{
    public function show(Request $request, string $code): Response|RedirectResponse
    {
        $canonicalCode = VerificationCode::normalize($code, VerificationCode::INSPECTION_PREFIX);

        if ($canonicalCode !== null && $canonicalCode !== $code) {
            return redirect()->route('inspections.public', $canonicalCode, 301);
        }

        $viewer = $request->user();

        $inspection = VehicleInspection::query()
            ->where('verification_code', $code)
            ->with('vehicle', 'workshop')
            ->first();

        // hiddenFrom, e não scopePubliclyVisible: laudo suprimido pela equipe some para o mundo e
        // continua respondendo para a oficina que o assinou, que é quem corrige
        // (App\Models\VehicleInspection::hiddenFrom).
        if ($inspection === null || $inspection->hiddenFrom($viewer)) {
            return response()->view('public.verification-invalid', [
                'typedCode' => $code,
            ], 404);
        }

        $publicUrl = $inspection->verificationUrl();

        return response()->view('public.inspection', [
            'inspection' => InspectionRedactor::redact($inspection, $viewer),
            'showsFullReport' => $inspection->showsFullReportTo($viewer),
            'maskedChassis' => VehicleIdentifierMask::chassis($inspection->vehicle?->chassis),
            'publicUrl' => $publicUrl,
            'qrSvg' => $publicUrl !== null ? VerificationQr::svg($publicUrl, 96) : null,
        ]);
    }
}
