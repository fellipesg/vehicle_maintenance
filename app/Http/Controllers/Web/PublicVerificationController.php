<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\VehicleInspection;
use App\Support\Maintenance\VerificationCode;
use App\Support\Vehicle\VehicleIdentifierMask;
use App\Support\VerificationQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Conferência pública sem login: /verificar (campo para digitar o código) e /v/{código} (a página
 * que o QR code do Selo da oficina abre). As duas passam pelo throttle:search.
 *
 * /verificar é o **único** campo público de código, e o produto tem dois documentos com código:
 * o Selo da oficina (RVL) e o laudo de vistoria cautelar (LDO, App\Http\Controllers\Web\
 * PublicInspectionController). Quem copia o código de um laudo digita aqui, então é aqui que ele é
 * resolvido — ver resolveTypedCode.
 */
class PublicVerificationController extends Controller
{
    /**
     * GET /verificar: sem ?codigo= mostra o campo; com um código no formato certo leva à página do
     * documento (selo ou laudo), que responde se ele existe. Formato inválido volta ao campo com o
     * erro (422).
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

        $destination = $this->resolveTypedCode($request->user(), $typedCode);

        if ($destination === null) {
            return response()->view('public.verify', [
                'typedCode' => $typedCode,
                'codeError' => 'Digite o código no formato '.VerificationCode::FORMAT_HINT.' ou '
                    .VerificationCode::formatHint(VerificationCode::INSPECTION_PREFIX)
                    .', como aparece no relatório ou no laudo.',
            ], 422);
        }

        return redirect()->to($destination);
    }

    /**
     * GET /v/{código}: o selo confirmado ou, se nenhum Selo da oficina tem esse código, a página de
     * código não encontrado com 404 e o campo para tentar outro. O código digitado em minúsculas
     * ou sem hífens é levado à forma canônica.
     */
    public function show(Request $request, string $code): Response|RedirectResponse
    {
        $canonicalCode = VerificationCode::normalize($code);

        if ($canonicalCode !== null && $canonicalCode !== $code) {
            return redirect()->route('verification.show', $canonicalCode, 301);
        }

        $maintenance = $this->findPubliclyVisibleMaintenance($code);

        if ($maintenance === null) {
            return response()->view('public.verification-invalid', [
                'typedCode' => $code,
            ], 404);
        }

        $verificationUrl = $maintenance->verificationUrl();
        $showsOwnerCta = $request->user() === null
            && $maintenance->vehicle !== null
            && ! $maintenance->vehicle->hasCurrentOwner();

        return response()->view('public.verification', [
            'maintenance' => $maintenance,
            'maskedChassis' => VehicleIdentifierMask::chassis($maintenance->vehicle?->chassis),
            'verificationUrl' => $verificationUrl,
            'qrSvg' => $verificationUrl ? VerificationQr::svg($verificationUrl, 96) : null,
            'showsOwnerCta' => $showsOwnerCta,
        ]);
    }

    /**
     * GET /v/{código}/sou-o-dono: o botão "Criar conta grátis" do cartão "Este carro é seu?". Só aqui
     * o destino do cadastro vai para a sessão (o "Adicionar veículo" com o aviso do CRLV-e), porque
     * ver o selo não deve mudar para onde um login posterior leva o visitante.
     */
    public function startOwnerSignup(Request $request, string $code): RedirectResponse
    {
        $canonicalCode = VerificationCode::normalize($code);

        if ($canonicalCode === null) {
            return redirect()->route('verification.lookup');
        }

        $maintenance = $this->findPubliclyVisibleMaintenance($canonicalCode);

        if ($maintenance === null) {
            return redirect()->route('verification.lookup');
        }

        if ($request->user() !== null) {
            return redirect()->route('home');
        }

        $vehicle = $maintenance->vehicle;

        if ($vehicle === null || $vehicle->hasCurrentOwner()) {
            return redirect()->route('verification.show', $canonicalCode);
        }

        // Mesmo caminho do /convite/{token}: o cadastro leva ao "Adicionar veículo" com o aviso do CRLV-e.
        $request->session()->put('url.intended', route('user.vehicles.create'));
        $request->session()->put('invite_notice', true);

        return redirect()->route('register');
    }

    /**
     * Para onde o código digitado leva, ou null quando nem como selo nem como laudo ele tem forma de
     * código.
     *
     * Com prefixo não há dúvida: RVL é selo, LDO é laudo, e a página de destino é que responde se
     * aquele código existe — perguntar aqui só adicionaria uma consulta. **Sem** prefixo o código é
     * ambíguo entre os dois documentos (VerificationCode::normalize devolve a forma canônica do
     * prefixo que o chamador pediu), e aí a existência desempata, na ordem de prefixes(): selo,
     * depois laudo. Não existindo nenhum, vai para a página do selo, que é a que explica o código
     * não encontrado.
     */
    private function resolveTypedCode(?User $viewer, string $typedCode): ?string
    {
        $sealCode = VerificationCode::normalize($typedCode);
        $inspectionCode = VerificationCode::normalize($typedCode, VerificationCode::INSPECTION_PREFIX);

        if ($sealCode === null && $inspectionCode === null) {
            return null;
        }

        if ($inspectionCode === null) {
            return route('verification.show', $sealCode);
        }

        if ($sealCode === null) {
            return route('inspections.public', $inspectionCode);
        }

        if ($this->findPubliclyVisibleMaintenance($sealCode) !== null) {
            return route('verification.show', $sealCode);
        }

        if ($this->findReadableInspection($viewer, $inspectionCode) !== null) {
            return route('inspections.public', $inspectionCode);
        }

        return route('verification.show', $sealCode);
    }

    /**
     * O laudo que este leitor consegue abrir: suprimido pela equipe some para o mundo e continua
     * respondendo para a oficina que o assinou (VehicleInspection::hiddenFrom).
     */
    private function findReadableInspection(?User $viewer, string $code): ?VehicleInspection
    {
        $inspection = VehicleInspection::query()->where('verification_code', $code)->first();

        if ($inspection === null || $inspection->hiddenFrom($viewer)) {
            return null;
        }

        return $inspection;
    }

    private function findPubliclyVisibleMaintenance(string $code): ?Maintenance
    {
        return Maintenance::query()
            ->where('verification_code', $code)
            ->whereNotNull('verified_at')
            ->whereNull('hidden_from_public_at')
            ->with('verifiedWorkshop', 'workshop', 'vehicle')
            ->first();
    }
}
