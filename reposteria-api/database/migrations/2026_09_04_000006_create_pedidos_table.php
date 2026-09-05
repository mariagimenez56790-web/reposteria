<?php

use App\Enums\PedidoEstado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->string('estado')->default(PedidoEstado::Pendiente->value)->index();
            $table->dateTime('fecha_pedido');
            $table->dateTime('fecha_entrega')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('total', 12, 2)->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['reposteria_id', 'fecha_pedido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
