<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('producto_variante_id')->nullable()->constrained('producto_variantes')->restrictOnDelete();
            $table->string('nombre_producto');
            $table->string('nombre_variante', 120)->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2)->unsigned();
            $table->decimal('subtotal', 12, 2)->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_detalles');
    }
};
