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
            if (! Schema::hasColumn('produto_materiais', 'material_id')) {
                // Mantém a coluna antiga material_produto_id para compatibilidade,
                // mas passa a usar material_id como padrão.
                $table->unsignedBigInteger('material_id')->nullable()->after('produto_id');
                $table->index(['empresa_id', 'produto_id', 'material_id'], 'produto_materiais_empresa_produto_material');
            }
        });

        // Backfill best-effort
        try {
            if (Schema::hasColumn('produto_materiais', 'material_id') && Schema::hasColumn('produto_materiais', 'material_produto_id')) {
                DB::statement('UPDATE produto_materiais SET material_id = COALESCE(material_id, material_produto_id)');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Unique nova (sem derrubar a antiga para evitar falhas em drivers diferentes)
        try {
            if (Schema::hasColumn('produto_materiais', 'material_id')) {
                Schema::table('produto_materiais', function (Blueprint $table) {
                    $table->unique(['empresa_id', 'produto_id', 'material_id'], 'produto_materiais_unique_vivo');
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        // Remover unique/índices criados e coluna nova (best-effort)
        try {
            Schema::table('produto_materiais', function (Blueprint $table) {
                try {
                    $table->dropUnique('produto_materiais_unique_vivo');
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $table->dropIndex('produto_materiais_empresa_produto_material');
                } catch (\Throwable $e) {
                    // ignore
                }

                if (Schema::hasColumn('produto_materiais', 'material_id')) {
                    try {
                        $table->dropColumn('material_id');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
