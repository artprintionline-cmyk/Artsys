<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_compras_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('produto_id')->index();
            $table->unsignedBigInteger('compra_item_id')->index();
            $table->decimal('quantidade_base', 12, 6);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('produto_id')->references('id')->on('produtos');
            $table->foreign('compra_item_id')->references('id')->on('compras_itens');

            $table->unique(['empresa_id', 'produto_id', 'compra_item_id'], 'produto_compras_itens_unique');
            $table->index(['empresa_id', 'produto_id'], 'produto_compras_itens_empresa_produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_compras_itens');
    }
};
