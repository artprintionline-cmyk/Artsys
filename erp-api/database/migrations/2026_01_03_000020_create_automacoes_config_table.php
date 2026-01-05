<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacoes_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('evento')->index();
            $table->string('acao')->index();
            $table->boolean('ativo')->default(false)->index();
            $table->json('parametros')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'evento', 'acao'], 'automacoes_config_unique_empresa_evento_acao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacoes_config');
    }
};
