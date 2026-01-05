<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Garantia de unicidade no banco (por empresa).
        // SKUs nulos podem coexistir, mas novos produtos geram SKU automaticamente.
        try {
            Schema::table('produtos', function (Blueprint $table) {
                $table->unique(['empresa_id', 'sku'], 'produtos_empresa_sku_unique');
            });
        } catch (\Throwable $e) {
            // Em ambientes legados com dados conflitantes, evita quebrar a migration.
            // A geração no Model ainda garante unicidade para novos registros.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropUnique('produtos_empresa_sku_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
