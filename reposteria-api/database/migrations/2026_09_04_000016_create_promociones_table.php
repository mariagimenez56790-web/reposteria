<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->restrictOnDelete();
            $table->string('nombre', 160);
            $table->text('descripcion')->nullable();
            $table->string('tipo_descuento', 30);
            $table->decimal('valor_descuento', 12, 2)->unsigned();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['reposteria_id', 'activo']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
