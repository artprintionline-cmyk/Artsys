<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_templates', 'tipo')) {
                $table->string('tipo')->default('texto')->after('conteudo');
            }
            if (! Schema::hasColumn('whatsapp_templates', 'template_nome')) {
                $table->string('template_nome')->nullable()->after('tipo');
            }
            if (! Schema::hasColumn('whatsapp_templates', 'template_linguagem')) {
                $table->string('template_linguagem')->default('pt_BR')->after('template_nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_templates', 'template_linguagem')) {
                $table->dropColumn('template_linguagem');
            }
            if (Schema::hasColumn('whatsapp_templates', 'template_nome')) {
                $table->dropColumn('template_nome');
            }
            if (Schema::hasColumn('whatsapp_templates', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
