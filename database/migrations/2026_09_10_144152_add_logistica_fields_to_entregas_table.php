<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            //
            $table->text('motivo_cancelacion')->nullable()->after('estado');
            $table->text('notas_despacho')->nullable()->after('motivo_cancelacion');
            $table->timestamp('cancelado_at')->nullable()->after('notas_despacho');
            $table->timestamp('entregado_at')->nullable()->after('cancelado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            //
            $table->dropColumn([
                'motivo_cancelacion',
                'notas_despacho',
                'cancelado_at',
                'entregado_at',
            ]);
        });
    }
};
