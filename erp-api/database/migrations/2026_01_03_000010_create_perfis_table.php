<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome'); // admin, operacional, financeiro, leitura
            $table->timestamps();

            $table->unique(['empresa_id', 'nome']);
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis');
    }
};
