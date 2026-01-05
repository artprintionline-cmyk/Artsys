<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_insumo_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('insumo_id')->index();
            $table->string('tipo'); // entrada/saida
            $table->decimal('quantidade', 12, 4);
            $table->string('origem'); // os, ajuste
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'origem', 'origem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_insumo_movimentacoes');
    }
};
