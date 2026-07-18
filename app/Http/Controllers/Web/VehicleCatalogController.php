<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\VehicleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleCatalogController extends Controller
{
    public function brands(VehicleCatalogService $catalog): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $catalog->brands(),
        ]);
    }

    public function models(Request $request, VehicleCatalogService $catalog): JsonResponse
    {
        $brand = $request->query('brand', '');

        return response()->json([
            'success' => true,
            'data' => $catalog->models($brand),
        ]);
    }
}
