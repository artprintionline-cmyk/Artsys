<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_configuracoes') || ! Schema::hasTable('automacoes_config')) {
            return;
        }

        // Migra flags antigas de automação do WhatsApp para a nova tabela automacoes_config.
        // Mantém o comportamento existente apenas para empresas que já tinham as flags ativadas.

        $hasEmProducao = Schema::hasColumn('whatsapp_configuracoes', 'auto_os_em_producao');
        $hasAguardPag = Schema::hasColumn('whatsapp_configuracoes', 'auto_os_aguardando_pagamento_pix');
        $hasFinalizada = Schema::hasColumn('whatsapp_configuracoes', 'auto_os_finalizada');

        if (! $hasEmProducao && ! $hasAguardPag && ! $hasFinalizada) {
            return;
        }

        $rows = DB::table('whatsapp_configuracoes')
            ->select(['empresa_id', 'status', 'auto_os_em_producao', 'auto_os_aguardando_pagamento_pix', 'auto_os_finalizada'])
            ->where('status', 'ativo')
            ->get();

        foreach ($rows as $r) {
            $empresaId = (int) $r->empresa_id;

            $map = [
                'whatsapp_os_em_producao' => $hasEmProducao ? (bool) ($r->auto_os_em_producao ?? false) : false,
                'whatsapp_os_aguardando_pagamento_pix' => $hasAguardPag ? (bool) ($r->auto_os_aguardando_pagamento_pix ?? false) : false,
                'whatsapp_os_finalizada' => $hasFinalizada ? (bool) ($r->auto_os_finalizada ?? false) : false,
            ];

            foreach ($map as $acao => $ativo) {
                if (! $ativo) {
                    continue;
                }

                DB::table('automacoes_config')->updateOrInsert(
                    ['empresa_id' => $empresaId, 'evento' => 'os_status_movida', 'acao' => $acao],
                    [
                        'ativo' => true,
                        'parametros' => '{}',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('automacoes_config')) {
            return;
        }

        DB::table('automacoes_config')
            ->where('evento', 'os_status_movida')
            ->whereIn('acao', ['whatsapp_os_em_producao', 'whatsapp_os_aguardando_pagamento_pix', 'whatsapp_os_finalizada'])
            ->delete();
    }
};
