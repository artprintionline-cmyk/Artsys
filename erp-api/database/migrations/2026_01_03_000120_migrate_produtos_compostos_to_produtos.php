<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('produtos_compostos') || ! Schema::hasTable('produto_composto_itens')) {
            return;
        }
        if (! Schema::hasTable('produtos') || ! Schema::hasTable('produto_materiais')) {
            return;
        }

        $now = Carbon::now();

        $compostos = DB::table('produtos_compostos')->orderBy('id')->get();

        foreach ($compostos as $c) {
            // Evitar duplicar caso já exista produto migrado
            $existing = DB::table('produtos')
                ->where('empresa_id', $c->empresa_id)
                ->where('legacy_produto_composto_id', $c->id)
                ->first();

            $produtoId = $existing?->id;

            if (! $produtoId) {
                $preco = (float) ($c->preco_base ?? 0);

                $produtoId = DB::table('produtos')->insertGetId([
                    'empresa_id' => $c->empresa_id,
                    'nome' => $c->nome,
                    'sku' => null,
                    'preco' => $preco,
                    'vendavel' => true,
                    'legacy_produto_composto_id' => $c->id,
                    'descricao' => $c->descricao,
                    'tipo_medida' => 'unitario',
                    'largura_padrao' => null,
                    'altura_padrao' => null,
                    'preco_manual' => $preco > 0 ? $preco : null,
                    'markup' => null,
                    'custo_calculado' => 0,
                    'preco_final' => $preco,
                    'status' => $c->status ?? 'ativo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $itens = DB::table('produto_composto_itens')
                ->where('produto_composto_id', $c->id)
                ->get();

            foreach ($itens as $it) {
                DB::table('produto_materiais')->updateOrInsert(
                    [
                        'empresa_id' => $c->empresa_id,
                        'produto_id' => $produtoId,
                        'material_produto_id' => $it->produto_id,
                    ],
                    [
                        'quantidade' => (float) $it->quantidade,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('produtos') || ! Schema::hasTable('produto_materiais')) {
            return;
        }

        $migrados = DB::table('produtos')
            ->whereNotNull('legacy_produto_composto_id')
            ->select(['id', 'empresa_id'])
            ->get();

        foreach ($migrados as $p) {
            DB::table('produto_materiais')
                ->where('empresa_id', $p->empresa_id)
                ->where('produto_id', $p->id)
                ->delete();
        }

        DB::table('produtos')
            ->whereNotNull('legacy_produto_composto_id')
            ->delete();
    }
};
