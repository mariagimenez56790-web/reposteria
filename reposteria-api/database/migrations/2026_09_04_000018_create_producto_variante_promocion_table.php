<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_variante_promocion')) {
            Schema::create('producto_variante_promocion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
                $table->foreignId('producto_variante_id')->constrained('producto_variantes')->restrictOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('producto_variante_promocion', function (Blueprint $table) {
            $table->unique(['promocion_id', 'producto_variante_id'], 'promo_variante_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variante_promocion');
    }
};
