<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_insumos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('produto_id')->index();
            $table->unsignedBigInteger('insumo_id')->index();
            $table->decimal('quantidade_base', 12, 4);
            $table->timestamps();

            $table->unique(['empresa_id', 'produto_id', 'insumo_id'], 'produto_insumos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_insumos');
    }
};
