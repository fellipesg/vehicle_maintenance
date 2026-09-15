<?php

namespace App\Support\Vehicle;

use App\Models\Vehicle;
use Carbon\CarbonInterface;

readonly class VehicleLookupResult
{
    public const MATCH_CHASSIS = 'chassis';

    public const MATCH_RENAVAM = 'renavam';

    public const MATCH_CURRENT_PLATE = 'current_plate';

    public const MATCH_PREVIOUS_PLATE = 'previous_plate';

    public function __construct(
        public Vehicle $vehicle,
        public string $matchedBy,
        public ?CarbonInterface $previousPlateEndedAt = null,
    ) {}
}
