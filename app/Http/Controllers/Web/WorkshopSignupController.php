<?php

namespace App\Http\Controllers\Web;

use App\Enums\RegistrationSource;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Workshop\ProfileController;
use App\Http\Requests\Web\StoreWorkshopSignupRequest;
use App\Models\WorkshopProspect;
use App\Services\Workshop\WorkshopSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Cadastro próprio de oficina (/para-oficinas/cadastro). O de proprietário continua em AuthController::register.
 */
class WorkshopSignupController extends Controller
{
    public const REGISTERED_MESSAGE = 'Oficina cadastrada. Enviamos as boas-vindas para :email. Próximo passo: complete o perfil e registre a primeira OS.';

    public function __construct(private WorkshopSignupService $signup) {}

    public function show(Request $request): View
    {
        $prospect = WorkshopProspect::fromRef($request->query('ref'));

        return view('workshops.signup', [
            'ref' => $prospect?->token,
            'defaults' => [
                'trade_name' => $prospect?->hasName() ? $prospect->displayName() : null,
                'cnpj' => $prospect?->formattedCnpj(),
                'email' => $prospect?->email,
            ],
            'stateOptions' => collect(ProfileController::STATES)
                ->mapWithKeys(fn (string $name, string $uf): array => [$uf => "{$name} ({$uf})"])
                ->all(),
        ]);
    }

    public function store(StoreWorkshopSignupRequest $request): RedirectResponse
    {
        $user = $this->signup->register($request->validated());

        UserRegistered::dispatch($user, RegistrationSource::Web);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('workshop.dashboard')
            ->with('success', str_replace(':email', $user->email, self::REGISTERED_MESSAGE))
            ->with('analytics_event', 'sign_up')
            ->with('analytics_method', 'workshop');
    }
}
