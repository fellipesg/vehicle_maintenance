<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    public function show(User $user): View
    {
        $user->load([
            'vehicles' => fn ($query) => $query
                ->where('user_vehicles.is_current_owner', true)
                ->with(['maintenances' => fn ($q) => $q->orderByDesc('maintenance_date')]),
        ]);

        return view('admin.users.show', compact('user'));
    }
}
