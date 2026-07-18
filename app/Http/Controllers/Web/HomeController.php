<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        if ($request->user()) {
            return view('home', ['user' => $request->user()]);
        }

        return view('home');
    }
}
