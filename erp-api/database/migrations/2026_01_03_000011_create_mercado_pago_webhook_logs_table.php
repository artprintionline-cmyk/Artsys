<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_webhook_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->string('payment_id')->nullable()->index();

            $table->string('x_request_id')->nullable();
            $table->boolean('assinatura_ok')->default(false);

            $table->string('mp_status')->nullable();
            $table->string('status_local')->nullable();
            $table->unsignedBigInteger('financeiro_lancamento_id')->nullable()->index();

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('resultado')->nullable(); // ok | ignorado | invalido | erro
            $table->text('mensagem')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('mp_payload')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'created_at'], 'mp_webhook_logs_empresa_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_webhook_logs');
    }
};
