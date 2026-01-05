<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_configuracoes', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_configuracoes', 'auto_os_em_producao')) {
                $table->boolean('auto_os_em_producao')->default(false)->after('api_version');
            }
            if (! Schema::hasColumn('whatsapp_configuracoes', 'auto_os_aguardando_pagamento_pix')) {
                $table->boolean('auto_os_aguardando_pagamento_pix')->default(false)->after('auto_os_em_producao');
            }
            if (! Schema::hasColumn('whatsapp_configuracoes', 'auto_os_finalizada')) {
                $table->boolean('auto_os_finalizada')->default(false)->after('auto_os_aguardando_pagamento_pix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_configuracoes', function (Blueprint $table) {
            foreach (['auto_os_finalizada', 'auto_os_aguardando_pagamento_pix', 'auto_os_em_producao'] as $col) {
                if (Schema::hasColumn('whatsapp_configuracoes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
