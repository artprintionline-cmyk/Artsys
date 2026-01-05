<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versao');
            $table->text('descricao')->nullable();
            $table->dateTime('aplicado_em')->nullable();
            $table->timestamps();

            $table->unique('versao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_versions');
    }
};
