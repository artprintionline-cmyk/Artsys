<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('financeiro_lancamento_id')->index();

            $table->string('metodo')->default('pix'); // pix | cartao | link
            $table->string('status')->default('pendente'); // pendente | pago | cancelado

            $table->string('payment_id')->nullable(); // Mercado Pago payment id
            $table->longText('qr_code_base64')->nullable();
            $table->longText('qr_code_text')->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'metodo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
