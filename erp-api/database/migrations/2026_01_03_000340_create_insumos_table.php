<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome');
            $table->string('sku')->nullable();
            $table->decimal('custo_unitario', 12, 4)->default(0);
            $table->string('unidade_medida', 20)->default('un');
            $table->boolean('controla_estoque')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'nome']);
            $table->unique(['empresa_id', 'sku'], 'insumos_empresa_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
