<?php

namespace App\Services;

use App\Jobs\ExecuteAutomacaoJob;
use App\Models\AutomacaoConfig;
use App\Models\AutomacaoExecucao;
use Illuminate\Support\Facades\DB;

class AutomacoesService
{
    /**
     * Lista de automações disponíveis (fixa), mas 100% opt-in por empresa via automacoes_config.
     * Sem "motor"; apenas uma lista de eventos/ações suportados.
     */
    public static function disponiveis(): array
    {
        return [
            [
                'evento' => 'os_criada',
                'acao' => 'whatsapp_os_criada',
                'titulo' => 'OS criada',
                'descricao' => 'Envia WhatsApp informando que a OS foi criada.',
                'parametros_schema' => [],
            ],
            [
                'evento' => 'os_status_movida',
                'acao' => 'whatsapp_os_em_producao',
                'titulo' => 'OS em produção',
                'descricao' => 'Envia WhatsApp quando a OS entra em produção.',
                'parametros_schema' => [],
            ],
            [
                'evento' => 'os_status_movida',
                'acao' => 'whatsapp_os_aguardando_pagamento_pix',
                'titulo' => 'OS aguardando pagamento (PIX)',
                'descricao' => 'Envia WhatsApp com PIX (copia e cola) quando existir QR gerado para a OS.',
                'parametros_schema' => [],
            ],
            [
                'evento' => 'os_status_movida',
                'acao' => 'whatsapp_os_finalizada',
                'titulo' => 'OS finalizada',
                'descricao' => 'Envia WhatsApp quando a OS for finalizada.',
                'parametros_schema' => [],
            ],
            [
                'evento' => 'financeiro_gerado',
                'acao' => 'whatsapp_financeiro_gerado',
                'titulo' => 'Financeiro gerado',
                'descricao' => 'Envia WhatsApp quando um lançamento financeiro é gerado.',
                'parametros_schema' => [],
            ],
            [
                'evento' => 'pagamento_confirmado',
                'acao' => 'whatsapp_pagamento_confirmado',
                'titulo' => 'Pagamento confirmado',
                'descricao' => 'Envia WhatsApp quando o pagamento for confirmado.',
                'parametros_schema' => [],
            ],
            [
                'evento' => 'financeiro_pendente',
                'acao' => 'whatsapp_lembrete_pagamento',
                'titulo' => 'Lembrete de pagamento (antes do vencimento)',
                'descricao' => 'Envia WhatsApp para lançamentos pendentes que vencem em X dias.',
                'parametros_schema' => [
                    ['key' => 'dias', 'label' => 'Dias (antes do vencimento)', 'type' => 'number', 'default' => 1],
                ],
            ],
            [
                'evento' => 'financeiro_vencido',
                'acao' => 'whatsapp_lembrete_pagamento',
                'titulo' => 'Lembrete de pagamento (vencido)',
                'descricao' => 'Envia WhatsApp para lançamentos pendentes vencidos há X dias.',
                'parametros_schema' => [
                    ['key' => 'dias', 'label' => 'Dias (após vencimento)', 'type' => 'number', 'default' => 1],
                ],
            ],
            [
                'evento' => 'os_parada',
                'acao' => 'whatsapp_notificar_os_parada',
                'titulo' => 'OS parada',
                'descricao' => 'Envia WhatsApp quando a OS ficar parada há X dias (sem mudança de status).',
                'parametros_schema' => [
                    ['key' => 'dias', 'label' => 'Dias parada', 'type' => 'number', 'default' => 3],
                ],
            ],
        ];
    }

    public static function isAutomacaoConhecida(string $evento, string $acao): bool
    {
        foreach (self::disponiveis() as $a) {
            if ($a['evento'] === $evento && $a['acao'] === $acao) {
                return true;
            }
        }

        return false;
    }

    public function listMerged(int $empresaId): array
    {
        $configs = AutomacaoConfig::query()
            ->where('empresa_id', $empresaId)
            ->get()
            ->keyBy(fn (AutomacaoConfig $c) => $c->evento . ':' . $c->acao);

        $out = [];

        foreach (self::disponiveis() as $def) {
            $key = $def['evento'] . ':' . $def['acao'];
            /** @var AutomacaoConfig|null $cfg */
            $cfg = $configs->get($key);

            $parametros = is_array($cfg?->parametros) ? $cfg->parametros : [];

            // aplica defaults do schema
            foreach (($def['parametros_schema'] ?? []) as $p) {
                $k = (string) ($p['key'] ?? '');
                if ($k !== '' && ! array_key_exists($k, $parametros) && array_key_exists('default', $p)) {
                    $parametros[$k] = $p['default'];
                }
            }

            $out[] = [
                'evento' => $def['evento'],
                'acao' => $def['acao'],
                'titulo' => $def['titulo'],
                'descricao' => $def['descricao'],
                'ativo' => (bool) ($cfg?->ativo ?? false),
                'parametros' => $parametros,
                'parametros_schema' => $def['parametros_schema'] ?? [],
            ];
        }

        return $out;
    }

    public function upsertConfig(int $empresaId, string $evento, string $acao, bool $ativo, array $parametros): AutomacaoConfig
    {
        /** @var AutomacaoConfig $cfg */
        $cfg = AutomacaoConfig::query()->updateOrCreate(
            ['empresa_id' => $empresaId, 'evento' => $evento, 'acao' => $acao],
            ['ativo' => $ativo, 'parametros' => $parametros]
        );

        return $cfg;
    }

    public function dispatchEvento(
        int $empresaId,
        string $evento,
        ?string $entidadeTipo,
        ?int $entidadeId,
        array $payload
    ): void {
        $configs = AutomacaoConfig::query()
            ->where('empresa_id', $empresaId)
            ->where('evento', $evento)
            ->where('ativo', true)
            ->get();

        foreach ($configs as $cfg) {
            $dedupeKey = $this->buildDedupeKey($cfg->evento, $cfg->acao, $entidadeTipo, $entidadeId, $payload);

            try {
                $exec = null;

                DB::transaction(function () use ($empresaId, $cfg, $evento, $entidadeTipo, $entidadeId, $dedupeKey, $payload, &$exec) {
                    $exec = AutomacaoExecucao::firstOrCreate(
                        [
                            'empresa_id' => $empresaId,
                            'automacao_config_id' => (int) $cfg->id,
                            'evento' => $evento,
                            'acao' => (string) $cfg->acao,
                            'entidade_tipo' => $entidadeTipo,
                            'entidade_id' => $entidadeId,
                            'dedupe_key' => $dedupeKey,
                        ],
                        [
                            'status' => 'queued',
                            'payload' => $payload,
                        ]
                    );
                });

                if ($exec && $exec->wasRecentlyCreated) {
                    dispatch(new ExecuteAutomacaoJob((int) $exec->id));
                }
            } catch (\Throwable $e) {
                // best-effort: nunca quebrar o fluxo principal
            }
        }
    }

    private function buildDedupeKey(
        string $evento,
        string $acao,
        ?string $entidadeTipo,
        ?int $entidadeId,
        array $payload
    ): string {
        $parts = [
            'evento' => $evento,
            'acao' => $acao,
            'entidade_tipo' => $entidadeTipo,
            'entidade_id' => $entidadeId,
        ];

        if (array_key_exists('status_novo', $payload)) {
            $parts['status_novo'] = (string) $payload['status_novo'];
        }

        if (array_key_exists('dias', $payload)) {
            $parts['dias'] = (string) $payload['dias'];
        }

        if (array_key_exists('data_ref', $payload)) {
            $parts['data_ref'] = (string) $payload['data_ref'];
        }

        return substr(sha1(json_encode($parts)), 0, 40);
    }
}
