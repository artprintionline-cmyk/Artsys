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
        Schema::create('os_itens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('ordem_servico_id')->index();
            $table->unsignedBigInteger('produto_id')->index();

            $table->decimal('quantidade', 10, 2);
            $table->decimal('largura', 8, 2)->nullable();
            $table->decimal('altura', 8, 2)->nullable();

            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('valor_total', 10, 2);

            $table->string('status')->default('ativo');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('os_itens');
    }
};
