<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->restrictOnDelete();
            $table->string('nombre', 160);
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['reposteria_id', 'activo']);
            $table->index(['reposteria_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
