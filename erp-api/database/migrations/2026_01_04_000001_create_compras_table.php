<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('compra_item_id')->index();

            $table->date('data');
            $table->string('fornecedor')->nullable();
            $table->decimal('quantidade', 14, 4);
            $table->decimal('valor_total', 12, 2);
            $table->decimal('custo_unitario', 12, 4)->default(0);
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'data']);
            $table->index(['empresa_id', 'compra_item_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
