<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use Illuminate\View\View;

class WorkshopController extends Controller
{
    public function index(): View
    {
        $workshops = Workshop::query()
            ->orderBy('name')
            ->paginate(25);

        return view('admin.workshops.index', compact('workshops'));
    }
}
