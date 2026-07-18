<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('user', 'workshop', 'garage') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET user_type = 'user' WHERE user_type = 'garage'");
            DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('user', 'workshop') NOT NULL DEFAULT 'user'");
        }
    }
};
