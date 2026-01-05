<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('acao');
            $table->string('entidade');
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->json('dados_anteriores')->nullable();
            $table->json('dados_novos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'entidade', 'entidade_id', 'created_at'], 'auditoria_empresa_entidade_ref');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_logs');
    }
};
