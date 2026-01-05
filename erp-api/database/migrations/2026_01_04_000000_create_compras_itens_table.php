<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();

            // material | insumo | equipamento
            $table->string('tipo', 20)->index();
            $table->string('nome');
            $table->string('unidade_compra', 20)->nullable();
            $table->boolean('ativo')->default(true);

            // Preço médio ponderado
            $table->decimal('preco_medio', 12, 4)->default(0);
            $table->decimal('preco_medio_qtd_base', 14, 4)->default(0);
            $table->decimal('preco_ultimo', 12, 4)->default(0);

            $table->timestamps();

            $table->index(['empresa_id', 'tipo', 'ativo']);
            $table->index(['empresa_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_itens');
    }
};
