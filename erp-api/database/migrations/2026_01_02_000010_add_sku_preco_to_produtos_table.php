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
            if (! Schema::hasColumn('produtos', 'sku')) {
                $table->string('sku')->nullable()->after('nome');
            }
            if (! Schema::hasColumn('produtos', 'preco')) {
                $table->decimal('preco', 10, 2)->default(0)->after('sku');
            }
        });

        if (Schema::hasColumn('produtos', 'preco')) {
            // Backfill: prefer preco_manual, then preco_final
            try {
                $precoManualExists = Schema::hasColumn('produtos', 'preco_manual');
                $precoFinalExists = Schema::hasColumn('produtos', 'preco_final');

                if ($precoManualExists && $precoFinalExists) {
                    DB::statement('UPDATE produtos SET preco = COALESCE(preco_manual, preco_final, preco)');
                } elseif ($precoManualExists) {
                    DB::statement('UPDATE produtos SET preco = COALESCE(preco_manual, preco)');
                } elseif ($precoFinalExists) {
                    DB::statement('UPDATE produtos SET preco = COALESCE(preco_final, preco)');
                }
            } catch (\Throwable $e) {
                // Safe no-op for DBs/drivers that may behave differently
            }
        }
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'preco')) {
                try {
                    $table->dropColumn('preco');
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            if (Schema::hasColumn('produtos', 'sku')) {
                try {
                    $table->dropColumn('sku');
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        });
    }
};
