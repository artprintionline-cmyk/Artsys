<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('cliente_id')->index();

            $table->string('numero');
            $table->text('descricao')->nullable();

            $table->decimal('valor_total', 10, 2)->default(0);

            $table->date('data_entrega');

            $table->string('status_atual')->default('criada');

            $table->timestamps();

            $table->unique(['empresa_id', 'numero'], 'ordens_empresa_numero_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordens_servico');
    }
};
