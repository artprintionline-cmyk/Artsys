<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('produtos', 'forma_calculo')) {
                // unitario | metro_linear | metro_quadrado
                $table->string('forma_calculo')->default('unitario')->after('tipo_medida');
                $table->index(['empresa_id', 'forma_calculo'], 'produtos_empresa_forma_calculo');
            }

            if (! Schema::hasColumn('produtos', 'preco_base')) {
                // preço por unidade/metro/m² conforme forma_calculo
                $table->decimal('preco_base', 10, 4)->default(0)->after('preco_venda');
            }
        });

        // Backfill best-effort
        try {
            if (Schema::hasColumn('produtos', 'tipo_medida') && Schema::hasColumn('produtos', 'forma_calculo')) {
                DB::statement("UPDATE produtos SET forma_calculo = COALESCE(NULLIF(forma_calculo, ''), tipo_medida)");
            }

            // preco_base: preferir preco_base já setado; fallback para preco_venda/preco/preco_final/preco_manual
            if (Schema::hasColumn('produtos', 'preco_base')) {
                $expr = [];
                $expr[] = 'NULLIF(preco_base, 0)';
                if (Schema::hasColumn('produtos', 'preco_venda')) {
                    $expr[] = 'NULLIF(preco_venda, 0)';
                }
                if (Schema::hasColumn('produtos', 'preco')) {
                    $expr[] = 'NULLIF(preco, 0)';
                }
                if (Schema::hasColumn('produtos', 'preco_final')) {
                    $expr[] = 'NULLIF(preco_final, 0)';
                }
                if (Schema::hasColumn('produtos', 'preco_manual')) {
                    $expr[] = 'NULLIF(preco_manual, 0)';
                }
                $expr[] = 'preco_base';

                DB::statement('UPDATE produtos SET preco_base = COALESCE(' . implode(',', $expr) . ')');
            }

            // manter compat: se existir preco_venda, alinhar a partir de preco_base
            if (Schema::hasColumn('produtos', 'preco_venda') && Schema::hasColumn('produtos', 'preco_base')) {
                DB::statement('UPDATE produtos SET preco_venda = COALESCE(NULLIF(preco_venda, 0), preco_base)');
            }
        } catch (Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            try {
                $table->dropIndex('produtos_empresa_forma_calculo');
            } catch (Throwable $e) {
                // ignore
            }

            foreach (['preco_base', 'forma_calculo'] as $col) {
                if (Schema::hasColumn('produtos', $col)) {
                    try {
                        $table->dropColumn($col);
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
            }
        });
    }
};
