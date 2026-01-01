<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_componentes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->index();

            $table->unsignedBigInteger('produto_id')->index();
            $table->unsignedBigInteger('componente_id')->index();

            $table->decimal('quantidade', 10, 4);
            $table->decimal('custo_unitario', 10, 2);
            $table->decimal('custo_total', 10, 2);

            $table->string('descricao')->nullable();
            $table->string('status')->default('ativo');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_componentes');
    }
};
