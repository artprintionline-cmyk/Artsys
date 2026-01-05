<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_materiais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('produto_id')->index();
            $table->unsignedBigInteger('material_produto_id')->index();
            $table->decimal('quantidade', 10, 4);
            $table->timestamps();

            $table->unique(['empresa_id', 'produto_id', 'material_produto_id'], 'produto_materiais_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_materiais');
    }
};
