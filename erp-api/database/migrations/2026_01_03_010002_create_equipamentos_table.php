<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome');
            $table->string('local_producao')->nullable();
            $table->enum('tipo_depreciacao', ['tempo', 'rendimento']);
            $table->decimal('valor_equipamento', 12, 2)->default(0);
            $table->unsignedInteger('vida_util_meses')->nullable();
            $table->unsignedBigInteger('rendimento_total')->nullable();
            $table->decimal('custo_por_uso', 12, 6)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'local_producao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamentos');
    }
};
