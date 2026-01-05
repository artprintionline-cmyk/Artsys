<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensagens', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_mensagens', 'direcao')) {
                $table->string('direcao')->default('saida')->after('mensagem');
            }
            if (! Schema::hasColumn('whatsapp_mensagens', 'tipo')) {
                $table->string('tipo')->default('text')->after('direcao');
            }
            if (! Schema::hasColumn('whatsapp_mensagens', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable()->after('tipo');
            }
            if (! Schema::hasColumn('whatsapp_mensagens', 'payload')) {
                $table->json('payload')->nullable()->after('referencia_id');
            }
            if (! Schema::hasColumn('whatsapp_mensagens', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            $table->index(['empresa_id', 'numero', 'created_at'], 'whatsapp_mensagens_empresa_numero_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensagens', function (Blueprint $table) {
            $table->dropIndex('whatsapp_mensagens_empresa_numero_created_at');

            if (Schema::hasColumn('whatsapp_mensagens', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
            if (Schema::hasColumn('whatsapp_mensagens', 'payload')) {
                $table->dropColumn('payload');
            }
            if (Schema::hasColumn('whatsapp_mensagens', 'provider_message_id')) {
                $table->dropColumn('provider_message_id');
            }
            if (Schema::hasColumn('whatsapp_mensagens', 'tipo')) {
                $table->dropColumn('tipo');
            }
            if (Schema::hasColumn('whatsapp_mensagens', 'direcao')) {
                $table->dropColumn('direcao');
            }
        });
    }
};
