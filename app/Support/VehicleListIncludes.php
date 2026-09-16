<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VehicleListIncludes
{
    /** @var list<string> */
    public const ALLOWED = ['plates', 'provenance_strip'];

    /**
     * @return list<string>
     */
    public static function parse(?string $include): array
    {
        if ($include === null || trim($include) === '') {
            return [];
        }

        $requested = array_map('trim', explode(',', $include));

        return array_values(array_intersect($requested, self::ALLOWED));
    }

    /**
     * @param  Builder<\App\Models\Vehicle>|BelongsToMany<\App\Models\Vehicle, \App\Models\User>  $query
     * @param  list<string>  $includes
     */
    public static function applyEagerLoads(Builder|BelongsToMany $query, array $includes): void
    {
        if (in_array('plates', $includes, true)) {
            $query->with([
                'plates' => fn ($q) => $q->orderByDesc('started_at')->orderByDesc('created_at'),
            ]);
        }

        if (in_array('provenance_strip', $includes, true)) {
            $query->with('provenanceStripMaintenances');
        }
    }
}
