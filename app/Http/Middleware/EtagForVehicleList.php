<?php

namespace App\Http\Middleware;

use App\Support\VehicleListIncludes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EtagForVehicleList
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $includes = VehicleListIncludes::parse($request->query('include'));
        sort($includes);

        $vehicleQuery = $user->currentVehicles();
        $vehicleCount = (clone $vehicleQuery)->count();
        $maxVehicleUpdated = (clone $vehicleQuery)->max('vehicles.updated_at');

        $pivotQuery = DB::table('user_vehicles')
            ->where('user_id', $user->id)
            ->where('is_current_owner', true);

        if ($user->tenant_id) {
            $pivotQuery->where('tenant_id', $user->tenant_id);
        }

        $maxPivotUpdated = $pivotQuery->max('updated_at');

        $fingerprint = implode('|', [
            (string) $user->id,
            (string) $vehicleCount,
            (string) $maxVehicleUpdated,
            (string) $maxPivotUpdated,
            (string) $request->query('page', '1'),
            (string) $request->query('per_page', '15'),
            implode(',', $includes),
        ]);

        $etag = 'W/"'.md5($fingerprint).'"';

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null && $this->etagMatches($ifNoneMatch, $etag)) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, no-cache');
        }

        $response = $next($request);

        return $response
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, no-cache');
    }

    private function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || $candidate === $etag) {
                return true;
            }
        }

        return false;
    }
}
