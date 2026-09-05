<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dateTime('fecha_pedido')->change();
            $table->dateTime('fecha_entrega')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('fecha_pedido')->change();
            $table->timestamp('fecha_entrega')->nullable()->change();
        });
    }
};
