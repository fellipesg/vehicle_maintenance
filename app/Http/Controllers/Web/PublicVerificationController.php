<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Support\Maintenance\VerificationCode;
use App\Support\Vehicle\VehicleIdentifierMask;
use App\Support\VerificationQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Conferência pública do Selo da oficina, sem login: /verificar (campo para digitar o código do
 * PDF) e /v/{código} (a página que o QR code abre). As duas passam pelo throttle:search.
 */
class PublicVerificationController extends Controller
{
    /**
     * GET /verificar: sem ?codigo= mostra o campo; com um código no formato certo leva a /v/{código},
     * que responde se o selo existe. Formato inválido volta ao campo com o erro (422).
     */
    public function lookup(Request $request): Response|RedirectResponse
    {
        $typedInput = $request->query('codigo');
        $typedCode = is_string($typedInput) ? trim($typedInput) : '';

        if ($typedCode === '') {
            return response()->view('public.verify', [
                'typedCode' => '',
                'codeError' => null,
            ]);
        }

        $code = VerificationCode::normalize($typedCode);

        if ($code === null) {
            return response()->view('public.verify', [
                'typedCode' => $typedCode,
                'codeError' => 'Digite o código no formato '.VerificationCode::FORMAT_HINT.', como aparece no relatório.',
            ], 422);
        }

        return redirect()->route('verification.show', $code);
    }

    /**
     * GET /v/{código}: o selo confirmado ou, se nenhum Selo da oficina tem esse código, a página de
     * código não encontrado com 404 e o campo para tentar outro. O código digitado em minúsculas
     * ou sem hífens é levado à forma canônica.
     */
    public function show(string $code): Response|RedirectResponse
    {
        $canonicalCode = VerificationCode::normalize($code);

        if ($canonicalCode !== null && $canonicalCode !== $code) {
            return redirect()->route('verification.show', $canonicalCode, 301);
        }

        $maintenance = Maintenance::query()
            ->where('verification_code', $code)
            ->whereNotNull('verified_at')
            ->whereNull('hidden_from_public_at')
            ->with('verifiedWorkshop', 'workshop', 'vehicle')
            ->first();

        if ($maintenance === null) {
            return response()->view('public.verification-invalid', [
                'typedCode' => $code,
            ], 404);
        }

        $verificationUrl = $maintenance->verificationUrl();

        return response()->view('public.verification', [
            'maintenance' => $maintenance,
            'maskedChassis' => VehicleIdentifierMask::chassis($maintenance->vehicle?->chassis),
            'verificationUrl' => $verificationUrl,
            'qrSvg' => $verificationUrl ? VerificationQr::svg($verificationUrl, 96) : null,
        ]);
    }
}
