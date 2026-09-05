<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('producto_variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
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
        Schema::dropIfExists('venta_detalles');
    }
};
