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
        if (DB::table('users')->selectRaw('LOWER(TRIM(email))')->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay correos de usuarios duplicados al ignorar mayúsculas y espacios. Revisa esas cuentas antes de migrar; no se eliminó ningún registro.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('invitacion_pendiente')->default(false);
            $table->string('invitacion_token_hash', 64)->nullable();
            $table->timestamp('invitacion_expira_at')->nullable();
            $table->timestamp('invitacion_enviada_at')->nullable();
            $table->timestamp('invitacion_revocada_at')->nullable();
            $table->unsignedInteger('sesion_version')->default(0);
        });
        DB::statement('CREATE UNIQUE INDEX users_email_normalizado_unique ON users (LOWER(TRIM(email)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX users_email_normalizado_unique');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitacion_pendiente', 'invitacion_token_hash', 'invitacion_expira_at', 'invitacion_enviada_at', 'invitacion_revocada_at', 'sesion_version']);
        });
    }
};
