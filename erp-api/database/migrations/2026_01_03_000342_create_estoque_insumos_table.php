<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_insumos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('insumo_id')->index();
            $table->decimal('quantidade_atual', 12, 4)->default(0);
            $table->decimal('estoque_minimo', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'insumo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_insumos');
    }
};
