<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait ResolvesPagination
{
    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->integer('per_page', $default);

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }

    protected function catalogLimit(Request $request, int $default = 500, int $max = 500): int
    {
        $limit = (int) $request->integer('limit', $default);

        if ($limit < 1) {
            return $default;
        }

        return min($limit, $max);
    }
}
