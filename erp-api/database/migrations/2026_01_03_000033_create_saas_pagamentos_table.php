<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assinatura_id');
            $table->decimal('valor', 10, 2);
            $table->string('status'); // pendente, pago, estornado
            $table->string('metodo')->nullable(); // manual, simulada, mercadopago (futuro)
            $table->string('referencia')->nullable();
            $table->dateTime('pago_em')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['assinatura_id', 'status']);
            $table->foreign('assinatura_id')->references('id')->on('assinaturas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_pagamentos');
    }
};
