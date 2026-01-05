<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->string('motivo')->nullable()->after('origem_id');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
