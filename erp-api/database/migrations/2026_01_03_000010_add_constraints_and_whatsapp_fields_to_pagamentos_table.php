<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            if (! Schema::hasColumn('pagamentos', 'ultimo_envio_whatsapp_at')) {
                $table->timestamp('ultimo_envio_whatsapp_at')->nullable()->after('payload');
            }

            if (! Schema::hasColumn('pagamentos', 'envios_whatsapp_count')) {
                $table->unsignedInteger('envios_whatsapp_count')->default(0)->after('ultimo_envio_whatsapp_at');
            }
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            // Unique por payment_id (Postgres permite múltiplos NULL)
            $table->unique('payment_id', 'pagamentos_payment_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropUnique('pagamentos_payment_id_unique');

            if (Schema::hasColumn('pagamentos', 'envios_whatsapp_count')) {
                $table->dropColumn('envios_whatsapp_count');
            }

            if (Schema::hasColumn('pagamentos', 'ultimo_envio_whatsapp_at')) {
                $table->dropColumn('ultimo_envio_whatsapp_at');
            }
        });
    }
};
