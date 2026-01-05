<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (!Schema::hasColumn('compras', 'compra_id')) {
                $table->unsignedBigInteger('compra_id')->nullable()->after('empresa_id')->index();
            }
        });

        // FK em migration separada pode falhar em alguns ambientes; manter apenas índice aqui.
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'compra_id')) {
                $table->dropColumn('compra_id');
            }
        });
    }
};
