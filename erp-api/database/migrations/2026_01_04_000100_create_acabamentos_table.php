<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acabamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome');
            $table->string('unidade_consumo', 20);
            $table->decimal('custo_unitario', 10, 6)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->unique(['empresa_id', 'nome'], 'acabamentos_unique_empresa_nome');
            $table->index(['empresa_id', 'unidade_consumo'], 'acabamentos_empresa_unidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acabamentos');
    }
};
