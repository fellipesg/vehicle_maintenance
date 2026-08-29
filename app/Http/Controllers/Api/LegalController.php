<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Legal', weight: 30)]
class LegalController extends Controller
{
    public function termsOfUse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'version' => config('legal.terms_version'),
                'content' => config('legal.terms_of_use'),
            ],
        ]);
    }
}
