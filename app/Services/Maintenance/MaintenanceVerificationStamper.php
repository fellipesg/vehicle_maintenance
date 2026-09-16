<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaintenanceVerificationStamper
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function stamp(Maintenance $maintenance, User $actor): Maintenance
    {
        if ($maintenance->verified_at !== null) {
            return $maintenance;
        }

        if ($actor->isWorkshop() && $actor->workshop && $maintenance->workshop_id === $actor->workshop->id) {
            $maintenance->forceFill([
                'registered_by_type' => 'workshop',
                'verified_at' => now(),
                'verified_workshop_id' => $actor->workshop->id,
                'verification_code' => $this->generateUniqueCode(),
            ])->save();

            return $maintenance->fresh();
        }

        $type = match (true) {
            $actor->isGarage() => 'garage',
            default => 'owner',
        };

        $maintenance->forceFill([
            'registered_by_type' => $type,
            'verified_at' => null,
            'verified_workshop_id' => null,
            'verification_code' => null,
        ])->save();

        return $maintenance->fresh();
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = 'RVL-'.$this->randomSegment(4).'-'.$this->randomSegment(2);

            if (! DB::table('maintenances')->where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar código de verificação único.');
    }

    private function randomSegment(int $length): string
    {
        $out = '';
        $max = strlen(self::CODE_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
