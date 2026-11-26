<?php

namespace App\Jobs;

use App\Jobs\Concerns\LimitsExecutionTime;
use App\Mail\VehicleMaintenancePdfMail;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\Vehicle\VehicleIdentifierVisibility;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class EmailVehicleMaintenancePdf implements ShouldQueue
{
    use LimitsExecutionTime;
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public User $user,
        public Vehicle $vehicle,
    ) {}

    public function handle(VehicleMaintenancePdfExporter $exporter): void
    {
        $this->limitExecutionTimeToJobTimeout();

        $file = $exporter->generate(
            $this->vehicle,
            maskIdentifiers: ! VehicleIdentifierVisibility::showsFullIdentifiers($this->user, $this->vehicle),
            viewer: $this->user,
        );

        try {
            Mail::to($this->user)->send(new VehicleMaintenancePdfMail(
                $this->vehicle,
                $file['content'],
                $file['filename'],
                $file['invoices'],
            ));
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }
}
