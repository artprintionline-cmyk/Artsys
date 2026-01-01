<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id');

            $table->string('nome');
            $table->text('descricao')->nullable();

            $table->string('tipo_medida'); // unitario, metro_quadrado, metro_linear

            $table->decimal('largura_padrao', 8, 2)->nullable();
            $table->decimal('altura_padrao', 8, 2)->nullable();

            $table->decimal('preco_manual', 10, 2)->nullable();
            $table->decimal('margem', 5, 2)->nullable();
            $table->decimal('custo_calculado', 10, 2)->default(0);
            $table->decimal('preco_final', 10, 2)->default(0);

            $table->string('status')->default('ativo');

            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
