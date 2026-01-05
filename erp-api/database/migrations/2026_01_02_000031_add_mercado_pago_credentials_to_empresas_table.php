<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'mercado_pago_access_token')) {
                $table->text('mercado_pago_access_token')->nullable()->after('status');
            }
            if (!Schema::hasColumn('empresas', 'mercado_pago_webhook_secret')) {
                $table->text('mercado_pago_webhook_secret')->nullable()->after('mercado_pago_access_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'mercado_pago_webhook_secret')) {
                $table->dropColumn('mercado_pago_webhook_secret');
            }
            if (Schema::hasColumn('empresas', 'mercado_pago_access_token')) {
                $table->dropColumn('mercado_pago_access_token');
            }
        });
    }
};
