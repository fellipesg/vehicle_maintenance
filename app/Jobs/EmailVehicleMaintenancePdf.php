<?php

namespace App\Jobs;

use App\Mail\VehicleMaintenancePdfMail;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class EmailVehicleMaintenancePdf implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public User $user,
        public Vehicle $vehicle,
    ) {}

    public function handle(VehicleMaintenancePdfExporter $exporter): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }

        $file = $exporter->generate($this->vehicle);

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
