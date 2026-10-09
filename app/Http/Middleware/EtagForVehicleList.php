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

        // A listagem traz maintenances_count, verified_maintenances_count e, com
        // ?include, a provenance_strip — todos mudam sem que vehicles.updated_at
        // mude. Sem este agregado, uma manutenção registrada em outro aparelho
        // (ou no portal) respondia 304 e o app seguia com a contagem velha.
        //
        // Uma query só: o teste de performance do my-vehicles limita o endpoint a
        // 12 e exige que o total não cresça com a quantidade de veículos.
        $maintenanceStats = DB::table('maintenances')
            ->whereIn('vehicle_id', (clone $vehicleQuery)->select('vehicles.id')->getQuery())
            ->selectRaw('count(*) as total, max(updated_at) as last_updated')
            ->first();

        $fingerprint = implode('|', [
            (string) $user->id,
            (string) $vehicleCount,
            (string) $maxVehicleUpdated,
            (string) $maxPivotUpdated,
            (string) ($maintenanceStats->total ?? 0),
            (string) ($maintenanceStats->last_updated ?? ''),
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
