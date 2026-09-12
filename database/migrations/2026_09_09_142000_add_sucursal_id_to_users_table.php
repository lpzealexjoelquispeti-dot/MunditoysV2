<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE users ADD COLUMN sucursal_id INTEGER NULL REFERENCES sucursales(id) ON DELETE SET NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('sucursal_id')
                    ->nullable()
                    ->constrained('sucursales')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_normalizado_unique');
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sucursal_id');
            });
            DB::statement('CREATE UNIQUE INDEX users_email_normalizado_unique ON users (LOWER(TRIM(email)))');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sucursal_id');
            });
        }
    }
};
