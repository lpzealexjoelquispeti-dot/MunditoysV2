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
        Schema::table('fotografias_modelado', function (Blueprint $table): void {
            $table->string('mascara')->nullable()->after('miniatura');
            $table->float('fraccion_mascara')->nullable()->after('mascara');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fotografias_modelado', function (Blueprint $table): void {
            $table->dropColumn(['mascara', 'fraccion_mascara']);
        });
    }
};
