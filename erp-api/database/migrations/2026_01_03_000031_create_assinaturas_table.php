<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('plano_id');
            $table->string('status'); // trial, ativa, suspensa, cancelada
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->timestamps();

            $table->unique('empresa_id');
            $table->index(['plano_id', 'status']);

            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('plano_id')->references('id')->on('planos')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas');
    }
};
