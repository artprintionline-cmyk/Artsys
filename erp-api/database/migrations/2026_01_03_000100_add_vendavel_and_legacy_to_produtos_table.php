<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('produtos', 'vendavel')) {
                $table->boolean('vendavel')->default(true)->after('preco');
            }
            if (! Schema::hasColumn('produtos', 'legacy_produto_composto_id')) {
                $table->unsignedBigInteger('legacy_produto_composto_id')->nullable()->after('vendavel');
                $table->index(['empresa_id', 'legacy_produto_composto_id'], 'produtos_empresa_legacy_composto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'legacy_produto_composto_id')) {
                try {
                    $table->dropIndex('produtos_empresa_legacy_composto');
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    $table->dropColumn('legacy_produto_composto_id');
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            if (Schema::hasColumn('produtos', 'vendavel')) {
                try {
                    $table->dropColumn('vendavel');
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        });
    }
};
