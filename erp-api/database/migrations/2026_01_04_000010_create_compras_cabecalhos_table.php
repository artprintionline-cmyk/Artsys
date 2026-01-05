<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_cabecalhos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();

            $table->date('data');
            $table->string('fornecedor')->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_cabecalhos');
    }
};
