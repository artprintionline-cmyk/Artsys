<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new column `markup` (nullable for safety)
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('markup', 10, 4)->nullable()->after('margem');
        });

        // Copy existing values from `margem` to `markup` if present
        DB::statement('UPDATE produtos SET markup = margem');

        // Attempt to drop old `margem` column; ignore failures to remain DB-agnostic
        if (Schema::hasColumn('produtos', 'margem')) {
            try {
                Schema::table('produtos', function (Blueprint $table) {
                    $table->dropColumn('margem');
                });
            } catch (\Throwable $e) {
                // Some drivers (SQLite) may not support dropping columns cleanly in this context.
                // Leaving the old column in place is safe; migration should still succeed.
            }
        }
    }

    public function down(): void
    {
        // Recreate `margem` and copy values back from `markup`, then drop `markup`
        Schema::table('produtos', function (Blueprint $table) {
            if (!Schema::hasColumn('produtos', 'margem')) {
                $table->decimal('margem', 10, 4)->nullable()->after('preco_manual');
            }
            if (!Schema::hasColumn('produtos', 'markup')) {
                $table->decimal('markup', 10, 4)->nullable()->after('preco_manual');
            }
        });

        DB::statement('UPDATE produtos SET margem = markup WHERE margem IS NULL');

        if (Schema::hasColumn('produtos', 'markup')) {
            try {
                Schema::table('produtos', function (Blueprint $table) {
                    $table->dropColumn('markup');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
