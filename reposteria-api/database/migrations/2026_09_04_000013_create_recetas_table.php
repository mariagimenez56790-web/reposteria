<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('nombre', 160);
            $table->decimal('rendimiento', 14, 3)->unsigned();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['reposteria_id', 'producto_id', 'nombre']);
            $table->index(['reposteria_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
