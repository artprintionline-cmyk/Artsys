<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('os_itens', function (Blueprint $table) {
            if (! Schema::hasColumn('os_itens', 'comprimento')) {
                $table->decimal('comprimento', 10, 4)->nullable()->after('quantidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('os_itens', function (Blueprint $table) {
            if (Schema::hasColumn('os_itens', 'comprimento')) {
                try {
                    $table->dropColumn('comprimento');
                } catch (Throwable $e) {
                    // ignore
                }
            }
        });
    }
};
