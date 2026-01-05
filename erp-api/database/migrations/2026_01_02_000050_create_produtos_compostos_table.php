<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_compostos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('preco_base', 10, 2)->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos_compostos');
    }
};
