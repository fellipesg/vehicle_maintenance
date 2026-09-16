<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Support\VerificationQr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVerificationController extends Controller
{
    public function show(Request $request, string $code): View
    {
        $maintenance = Maintenance::query()
            ->where('verification_code', $code)
            ->whereNotNull('verified_at')
            ->with('verifiedWorkshop', 'vehicle')
            ->first();

        if ($maintenance === null) {
            return view('public.verification-invalid');
        }

        $chassis = (string) ($maintenance->vehicle?->chassis ?? '');
        $maskedChassis = strlen($chassis) >= 9
            ? str_repeat('*', 9).substr($chassis, 9)
            : $chassis;

        $verificationUrl = $maintenance->verificationUrl();

        return view('public.verification', [
            'maintenance' => $maintenance,
            'maskedChassis' => $maskedChassis,
            'qrSvg' => $verificationUrl ? VerificationQr::svg($verificationUrl, 96) : null,
        ]);
    }
}
