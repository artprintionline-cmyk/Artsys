<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custos_mao_obra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('funcao');
            $table->decimal('custo_hora', 12, 2)->default(0);
            $table->string('centro_custo')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'centro_custo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custos_mao_obra');
    }
};
