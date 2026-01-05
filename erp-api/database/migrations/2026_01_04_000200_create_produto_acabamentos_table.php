<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_acabamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('acabamento_id');
            $table->decimal('quantidade_base', 10, 6)->default(1);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('produto_id')->references('id')->on('produtos');
            $table->foreign('acabamento_id')->references('id')->on('acabamentos');

            $table->unique(['empresa_id', 'produto_id', 'acabamento_id'], 'produto_acabamentos_unique');
            $table->index(['empresa_id', 'produto_id'], 'produto_acabamentos_empresa_produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_acabamentos');
    }
};
