<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->decimal('precio', 12, 2)->unsigned();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['producto_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
    }
};
