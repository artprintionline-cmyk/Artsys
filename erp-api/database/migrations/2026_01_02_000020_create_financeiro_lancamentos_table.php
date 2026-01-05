<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financeiro_lancamentos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('ordem_servico_id')->index();
            $table->unsignedBigInteger('cliente_id')->index();

            $table->string('tipo'); // receber | pagar
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->string('status')->default('pendente'); // pendente | pago | cancelado
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financeiro_lancamentos');
    }
};
