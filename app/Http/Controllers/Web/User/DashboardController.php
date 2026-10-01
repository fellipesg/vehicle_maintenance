<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Services\User\OwnerDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Início do proprietário, renderizado no servidor: KPIs, próxima revisão, pendências e, no
     * primeiro uso, os primeiros passos.
     */
    public function index(Request $request, OwnerDashboard $dashboard): View
    {
        return view('user.dashboard', [
            'user' => $request->user(),
            'dashboard' => $dashboard->build($request->user()),
        ]);
    }
}
