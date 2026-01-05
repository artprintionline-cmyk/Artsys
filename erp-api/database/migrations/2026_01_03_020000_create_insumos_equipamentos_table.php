<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos_equipamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nome');
            $table->string('unidade_consumo');
            $table->decimal('custo_unitario', 10, 4);
            $table->unsignedBigInteger('equipamento_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('equipamento_id')->references('id')->on('equipamentos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos_equipamentos');
    }
};
