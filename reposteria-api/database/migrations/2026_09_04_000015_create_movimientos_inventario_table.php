<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->restrictOnDelete();
            $table->foreignId('ingrediente_id')->constrained('ingredientes')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->decimal('cantidad', 14, 3)->unsigned();
            $table->decimal('stock_anterior', 14, 3)->unsigned();
            $table->decimal('stock_nuevo', 14, 3)->unsigned();
            $table->string('motivo')->nullable();
            $table->string('referencia_tipo', 80)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_movimiento');
            $table->timestamps();
            $table->index(['reposteria_id', 'fecha_movimiento']);
            $table->index(['ingrediente_id', 'fecha_movimiento']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
