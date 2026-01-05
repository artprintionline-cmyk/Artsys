<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_auditorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'acao']);
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_auditorias');
    }
};
