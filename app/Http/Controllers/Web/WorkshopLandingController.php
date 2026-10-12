<?php

namespace App\Http\Controllers\Web;

use App\Enums\WorkshopMessageTrigger;
use App\Http\Controllers\Controller;
use App\Models\WorkshopProspect;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página pública para oficinas (/para-oficinas). O ?ref= do e-mail de prospecção só é repassado ao
 * cadastro quando aponta para uma prospect real.
 */
class WorkshopLandingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $prospect = WorkshopProspect::fromRef($request->query('ref'));

        return view('workshops.landing', [
            'signupUrl' => route('workshops.signup', array_filter(['ref' => $prospect?->token])),
            // Só os gatilhos que o WorkshopMessageTemplateDispatcher realmente dispara hoje.
            'followUps' => [WorkshopMessageTrigger::ScheduledRevision, WorkshopMessageTrigger::CorrectiveFollowUp],
        ]);
    }
}
