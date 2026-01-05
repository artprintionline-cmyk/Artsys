<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_composto_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_composto_id')->index();
            $table->unsignedBigInteger('produto_id')->index();
            $table->decimal('quantidade', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_composto_itens');
    }
};
