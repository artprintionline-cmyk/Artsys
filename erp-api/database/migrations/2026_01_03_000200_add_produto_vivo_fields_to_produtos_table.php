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
            if (! Schema::hasColumn('produtos', 'custo_base')) {
                $table->decimal('custo_base', 10, 2)->default(0)->after('preco');
            }
            if (! Schema::hasColumn('produtos', 'preco_venda')) {
                $table->decimal('preco_venda', 10, 2)->default(0)->after('custo_base');
            }
            if (! Schema::hasColumn('produtos', 'custo_total')) {
                $table->decimal('custo_total', 10, 2)->default(0)->after('preco_venda');
            }
            if (! Schema::hasColumn('produtos', 'lucro')) {
                $table->decimal('lucro', 10, 2)->default(0)->after('custo_total');
            }
            if (! Schema::hasColumn('produtos', 'margem_percentual')) {
                $table->decimal('margem_percentual', 10, 2)->default(0)->after('lucro');
            }
            if (! Schema::hasColumn('produtos', 'controla_estoque')) {
                $table->boolean('controla_estoque')->default(true)->after('margem_percentual');
            }
            if (! Schema::hasColumn('produtos', 'ativo')) {
                $table->boolean('ativo')->default(true)->after('controla_estoque');
                $table->index(['empresa_id', 'ativo'], 'produtos_empresa_ativo');
            }
        });

        // Backfills best-effort (não deve falhar em ambientes diferentes)
        try {
            // preco_venda: preferir `preco` (já usado pela UI), fallback para `preco_final` / `preco_manual`
            $hasPreco = Schema::hasColumn('produtos', 'preco');
            $hasPrecoFinal = Schema::hasColumn('produtos', 'preco_final');
            $hasPrecoManual = Schema::hasColumn('produtos', 'preco_manual');

            if ($hasPreco) {
                DB::statement('UPDATE produtos SET preco_venda = COALESCE(preco_venda, preco)');
            }
            if ($hasPrecoFinal) {
                DB::statement('UPDATE produtos SET preco_venda = COALESCE(NULLIF(preco_venda, 0), preco_final, preco_venda)');
            }
            if ($hasPrecoManual) {
                DB::statement('UPDATE produtos SET preco_venda = COALESCE(NULLIF(preco_venda, 0), preco_manual, preco_venda)');
            }

            // custo_total: fallback para custo_calculado (legado)
            if (Schema::hasColumn('produtos', 'custo_calculado')) {
                DB::statement('UPDATE produtos SET custo_total = COALESCE(NULLIF(custo_total, 0), custo_calculado, custo_total)');
            }

            // ativo: derivar de status legado quando existir
            if (Schema::hasColumn('produtos', 'status')) {
                DB::statement("UPDATE produtos SET ativo = CASE WHEN status = 'ativo' THEN 1 ELSE 0 END");
            }

            // lucro e margem
            DB::statement('UPDATE produtos SET lucro = (preco_venda - custo_total)');
            DB::statement('UPDATE produtos SET margem_percentual = CASE WHEN custo_total > 0 THEN ((lucro / custo_total) * 100) ELSE 0 END');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Remover índice criado, se existir
            try {
                $table->dropIndex('produtos_empresa_ativo');
            } catch (\Throwable $e) {
                // ignore
            }

            foreach (['ativo', 'controla_estoque', 'margem_percentual', 'lucro', 'custo_total', 'preco_venda', 'custo_base'] as $col) {
                if (Schema::hasColumn('produtos', $col)) {
                    try {
                        $table->dropColumn($col);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
        });
    }
};
