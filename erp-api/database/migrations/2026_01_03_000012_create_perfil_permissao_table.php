<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfil_permissao', function (Blueprint $table) {
            $table->unsignedBigInteger('perfil_id');
            $table->unsignedBigInteger('permissao_id');

            $table->primary(['perfil_id', 'permissao_id']);

            $table->foreign('perfil_id')->references('id')->on('perfis')->onDelete('cascade');
            $table->foreign('permissao_id')->references('id')->on('permissoes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_permissao');
    }
};
