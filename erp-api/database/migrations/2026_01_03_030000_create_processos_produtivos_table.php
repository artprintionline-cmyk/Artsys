<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processos_produtivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome');
            $table->string('unidade_consumo');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nome'], 'processos_produtivos_unique_empresa_nome');
            $table->index(['empresa_id', 'unidade_consumo'], 'processos_produtivos_empresa_unidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processos_produtivos');
    }
};
