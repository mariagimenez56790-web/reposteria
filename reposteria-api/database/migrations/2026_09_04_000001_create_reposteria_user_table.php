<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reposteria_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reposteria_id')->constrained('reposterias')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reposteria_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reposteria_user');
    }
};
