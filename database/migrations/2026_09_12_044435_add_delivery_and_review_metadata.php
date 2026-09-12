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
        Schema::table('ventas', function (Blueprint $table): void {
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->json('cotizacion_envio')->nullable();
        });
        Schema::table('entregas', fn (Blueprint $table) => $table->json('destino_snapshot')->nullable());
        Schema::table('resenas', fn (Blueprint $table) => $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete());
        Schema::table('notificaciones', function (Blueprint $table): void {
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('clave_evento')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('producto_id');
            $table->dropUnique(['clave_evento']);
            $table->dropColumn('clave_evento');
        });
        Schema::table('resenas', fn (Blueprint $table) => $table->dropConstrainedForeignId('venta_id'));
        Schema::table('entregas', fn (Blueprint $table) => $table->dropColumn('destino_snapshot'));
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn(['costo_envio', 'cotizacion_envio']));
    }
};
