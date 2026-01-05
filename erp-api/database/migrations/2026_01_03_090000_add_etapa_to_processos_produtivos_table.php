<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processos_produtivos', function (Blueprint $table) {
            if (! Schema::hasColumn('processos_produtivos', 'etapa')) {
                $table->string('etapa', 30)->default('acabamentos')->after('unidade_consumo');
                $table->index(['empresa_id', 'etapa']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('processos_produtivos', function (Blueprint $table) {
            if (Schema::hasColumn('processos_produtivos', 'etapa')) {
                $table->dropIndex(['empresa_id', 'etapa']);
                $table->dropColumn('etapa');
            }
        });
    }
};
