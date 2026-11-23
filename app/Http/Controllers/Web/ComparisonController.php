<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ComparisonController extends Controller
{
    public function __invoke(): View
    {
        return view('comparison.show');
    }
}
