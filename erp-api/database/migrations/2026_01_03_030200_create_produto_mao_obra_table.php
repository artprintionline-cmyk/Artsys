<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_mao_obra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('produto_id')->index();
            $table->unsignedBigInteger('custo_mao_obra_id')->index();
            $table->decimal('minutos_base', 10, 2);
            $table->timestamps();

            $table->unique(['empresa_id', 'produto_id', 'custo_mao_obra_id'], 'produto_mao_obra_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_mao_obra');
    }
};
