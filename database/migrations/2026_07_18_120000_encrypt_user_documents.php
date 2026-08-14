<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->text('document')->nullable()->change();
            });

            Schema::table('user_vehicles', function (Blueprint $table) {
                $table->text('owner_document')->nullable()->change();
            });
        }

        $this->encryptColumn('users', 'document');
        $this->encryptColumn('user_vehicles', 'owner_document');
    }

    public function down(): void
    {
        $this->decryptColumn('users', 'document');
        $this->decryptColumn('user_vehicles', 'owner_document');

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('document', 20)->nullable()->change();
            });

            Schema::table('user_vehicles', function (Blueprint $table) {
                $table->string('owner_document', 20)->nullable()->change();
            });
        }
    }

    private function encryptColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function (object $row) use ($table, $column): void {
                $value = $row->{$column};
                if ($value === null || $value === '' || $this->looksEncrypted((string) $value)) {
                    return;
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => Crypt::encryptString((string) $value),
                ]);
            });
    }

    private function decryptColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function (object $row) use ($table, $column): void {
                $value = $row->{$column};
                if ($value === null || $value === '' || ! $this->looksEncrypted((string) $value)) {
                    return;
                }

                try {
                    $plain = Crypt::decryptString((string) $value);
                } catch (Throwable) {
                    return;
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => mb_substr($plain, 0, 20),
                ]);
            });
    }

    private function looksEncrypted(string $value): bool
    {
        if (! str_starts_with($value, 'eyJ')) {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
