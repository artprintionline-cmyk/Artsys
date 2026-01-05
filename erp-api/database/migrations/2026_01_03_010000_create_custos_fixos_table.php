<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custos_fixos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('descricao');
            $table->decimal('valor_mensal', 12, 2)->default(0);
            $table->string('centro_custo')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'centro_custo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custos_fixos');
    }
};
