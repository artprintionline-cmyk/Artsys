<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->string('versao_alvo');
            $table->text('descricao')->nullable();
            $table->string('status', 20)->default('pending'); // pending|applied|failed
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->longText('log')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['versao_alvo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
