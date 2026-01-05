<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensagens', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_mensagens', 'ordem_servico_id')) {
                $table->unsignedBigInteger('ordem_servico_id')->nullable()->index()->after('cliente_id');
            }

            if (! Schema::hasColumn('whatsapp_mensagens', 'cliente_id')) {
                $table->unsignedBigInteger('cliente_id')->nullable()->index()->after('empresa_id');
            }

            $table->index(['empresa_id', 'ordem_servico_id', 'created_at'], 'whatsapp_mensagens_empresa_os_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensagens', function (Blueprint $table) {
            try {
                $table->dropIndex('whatsapp_mensagens_empresa_os_created_at');
            } catch (\Throwable $e) {
                // best-effort
            }

            if (Schema::hasColumn('whatsapp_mensagens', 'ordem_servico_id')) {
                $table->dropColumn('ordem_servico_id');
            }
        });
    }
};
