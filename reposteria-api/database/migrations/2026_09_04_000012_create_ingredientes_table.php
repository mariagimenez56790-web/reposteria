<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->restrictOnDelete();
            $table->string('nombre', 160);
            $table->string('unidad_medida', 30);
            $table->decimal('stock_actual', 14, 3)->unsigned()->default(0);
            $table->decimal('stock_minimo', 14, 3)->unsigned()->default(0);
            $table->decimal('costo_unitario', 12, 2)->unsigned()->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['reposteria_id', 'nombre']);
            $table->index(['reposteria_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredientes');
    }
};
