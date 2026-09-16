<?php

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('registered_by_type', 20)->default('owner')->after('user_id');
            $table->timestamp('verified_at')->nullable()->after('registered_by_type');
            $table->foreignId('verified_workshop_id')->nullable()->after('verified_at')->constrained('workshops')->nullOnDelete();
            $table->string('verification_code', 12)->nullable()->unique()->after('verified_workshop_id');

            $table->index('registered_by_type');
            $table->index('verification_code');
        });

        $this->backfillProvenance();
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropIndex(['registered_by_type']);
            $table->dropIndex(['verification_code']);
            $table->dropConstrainedForeignId('verified_workshop_id');
            $table->dropColumn(['registered_by_type', 'verified_at', 'verification_code']);
        });
    }

    private function backfillProvenance(): void
    {
        Maintenance::query()->with('user')->orderBy('id')->each(function (Maintenance $maintenance): void {
            $user = $maintenance->user;
            $type = 'owner';

            if ($user instanceof User) {
                if ($user->user_type === 'garage') {
                    $type = 'garage';
                } elseif ($user->user_type === 'workshop' && $maintenance->workshop_id !== null) {
                    $type = 'workshop';
                }
            }

            $updates = ['registered_by_type' => $type];

            if ($type === 'workshop') {
                $updates['verified_at'] = $maintenance->created_at;
                $updates['verified_workshop_id'] = $maintenance->workshop_id;
                $updates['verification_code'] = $this->generateUniqueCode();
            }

            $maintenance->forceFill($updates)->saveQuietly();
        });
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $segment = '';
            for ($i = 0; $i < 4; $i++) {
                $segment .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $suffix = '';
            for ($i = 0; $i < 2; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'RVL-'.$segment.'-'.$suffix;

            if (! DB::table('maintenances')->where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        return 'RVL-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(2));
    }
};
