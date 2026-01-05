<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produto_materiais', function (Blueprint $table) {
            if (! Schema::hasColumn('produto_materiais', 'quantidade_base')) {
                // quantidade por unidade/metro/m² conforme forma_calculo do produto final
                $table->decimal('quantidade_base', 10, 4)->default(0)->after('quantidade');
            }
        });

        try {
            if (Schema::hasColumn('produto_materiais', 'quantidade') && Schema::hasColumn('produto_materiais', 'quantidade_base')) {
                DB::statement('UPDATE produto_materiais SET quantidade_base = COALESCE(NULLIF(quantidade_base, 0), quantidade)');
            }
        } catch (Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::table('produto_materiais', function (Blueprint $table) {
            if (Schema::hasColumn('produto_materiais', 'quantidade_base')) {
                try {
                    $table->dropColumn('quantidade_base');
                } catch (Throwable $e) {
                    // ignore
                }
            }
        });
    }
};
