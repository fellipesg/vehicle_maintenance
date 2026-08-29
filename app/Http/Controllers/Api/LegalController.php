<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LegalDocumentResource;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Legal', weight: 30)]
class LegalController extends Controller
{
    public function termsOfUse(): JsonResponse
    {
        return ApiResponse::success(new LegalDocumentResource([
            'version' => config('legal.terms_version'),
            'content' => config('legal.terms_of_use'),
        ]));
    }
}
