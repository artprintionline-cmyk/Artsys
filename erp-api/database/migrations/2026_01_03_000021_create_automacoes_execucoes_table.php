<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacoes_execucoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('automacao_config_id')->nullable()->index();

            $table->string('evento')->index();
            $table->string('acao')->index();

            $table->string('entidade_tipo')->nullable()->index();
            $table->unsignedBigInteger('entidade_id')->nullable()->index();

            $table->string('dedupe_key')->nullable();

            $table->string('status')->default('queued')->index();
            $table->text('mensagem')->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->unique([
                'empresa_id',
                'automacao_config_id',
                'entidade_tipo',
                'entidade_id',
                'dedupe_key',
            ], 'automacoes_execucoes_unique_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacoes_execucoes');
    }
};
