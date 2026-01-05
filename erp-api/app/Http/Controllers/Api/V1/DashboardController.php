<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\OrdemServico;
use App\Models\FinanceiroLancamento;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private const STATUS_CANONICOS = [
        'aberta',
        'em_producao',
        'aguardando_pagamento',
        'finalizada',
        'cancelada',
    ];

    private function normalizeStatus(string $status): string
    {
        $s = trim($status);

        if ($s === 'criada') {
            return 'aberta';
        }

        if (in_array($s, ['em_andamento', 'producao'], true)) {
            return 'em_producao';
        }

        if (in_array($s, ['faturado', 'pendencia', 'pendente'], true)) {
            return 'aguardando_pagamento';
        }

        return $s;
    }

    /** @return array{periodo:string,inicio:Carbon,fim:Carbon,anterior_inicio:Carbon,anterior_fim:Carbon} */
    private function periodFromRequest(Request $request): array
    {
        $raw = strtolower((string) $request->query('periodo', 'mes'));
        $periodo = in_array($raw, ['hoje', 'semana', 'mes'], true) ? $raw : 'mes';

        $now = now();

        if ($periodo === 'hoje') {
            $inicio = $now->copy()->startOfDay();
            $fim = $now->copy()->endOfDay();
            $anterior_inicio = $inicio->copy()->subDay();
            $anterior_fim = $fim->copy()->subDay();
        } elseif ($periodo === 'semana') {
            $inicio = $now->copy()->startOfWeek();
            $fim = $now->copy()->endOfWeek();
            $anterior_inicio = $inicio->copy()->subWeek();
            $anterior_fim = $fim->copy()->subWeek();
        } else {
            $inicio = $now->copy()->startOfMonth();
            $fim = $now->copy()->endOfMonth();
            $anterior_inicio = $inicio->copy()->subMonthNoOverflow();
            $anterior_fim = $fim->copy()->subMonthNoOverflow();
        }

        return compact('periodo', 'inicio', 'fim', 'anterior_inicio', 'anterior_fim');
    }

    private function cacheKey(int $empresaId, string $endpoint, string $periodo, string $extra = ''): string
    {
        $suffix = $extra !== '' ? (':' . $extra) : '';
        return "dashboard:{$empresaId}:{$endpoint}:{$periodo}{$suffix}";
    }

    private function trend(float $atual, float $anterior): array
    {
        if ($anterior == 0.0) {
            return [
                'atual' => $atual,
                'anterior' => $anterior,
                'direcao' => $atual > 0 ? 'up' : 'flat',
            ];
        }

        if ($atual > $anterior) {
            return ['atual' => $atual, 'anterior' => $anterior, 'direcao' => 'up'];
        }
        if ($atual < $anterior) {
            return ['atual' => $atual, 'anterior' => $anterior, 'direcao' => 'down'];
        }
        return ['atual' => $atual, 'anterior' => $anterior, 'direcao' => 'flat'];
    }

    public function summary(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json([
                'total_os' => 0,
                'em_producao' => 0,
                'faturado' => 0,
                'pendencias' => 0,
            ]);
        }

        $total = OrdemServico::where('empresa_id', $empresaId)->count();

        $emProducao = OrdemServico::where('empresa_id', $empresaId)
            ->whereIn('status_atual', ['producao', 'em_andamento', 'em_producao'])
            ->count();

        $faturado = OrdemServico::where('empresa_id', $empresaId)
            ->whereIn('status_atual', ['faturado', 'aguardando_pagamento'])
            ->count();

        $pendencias = OrdemServico::where('empresa_id', $empresaId)
            ->whereIn('status_atual', ['pendencia', 'pendente'])
            ->count();

        return response()->json([
            'total_os' => $total,
            'em_producao' => $emProducao,
            'faturado' => $faturado,
            'pendencias' => $pendencias,
        ]);
    }

    public function resumo(Request $request): JsonResponse
    {
        $empresaId = (int) ($request->attributes->get('empresa_id') ?? 0);
        if ($empresaId <= 0) {
            return response()->json(['message' => 'Empresa não informada.'], 400);
        }

        $period = $this->periodFromRequest($request);
        $ttl = 60;

        $data = Cache::remember(
            $this->cacheKey($empresaId, 'resumo', $period['periodo']),
            $ttl,
            function () use ($empresaId, $period) {
                $inicio = $period['inicio'];
                $fim = $period['fim'];

                $osAbertas = OrdemServico::query()
                    ->where('empresa_id', $empresaId)
                    ->whereIn('status_atual', ['aberta', 'criada'])
                    ->count();

                $osEmProducao = OrdemServico::query()
                    ->where('empresa_id', $empresaId)
                    ->whereIn('status_atual', ['em_producao', 'em_andamento', 'producao'])
                    ->count();

                $osFinalizadasPeriodo = (int) DB::table('os_historico')
                    ->where('empresa_id', $empresaId)
                    ->where('status_novo', 'finalizada')
                    ->whereBetween('created_at', [$inicio, $fim])
                    ->count();

                $faturamentoPagoPeriodo = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pago')
                    ->whereBetween('data_pagamento', [$inicio->toDateString(), $fim->toDateString()])
                    ->sum('valor');

                $valorPendente = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pendente')
                    ->whereBetween('data_vencimento', [$inicio->toDateString(), $fim->toDateString()])
                    ->sum('valor');

                $inadimplenciaTotal = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pendente')
                    ->whereBetween('data_vencimento', [$inicio->toDateString(), $fim->toDateString()])
                    ->where('data_vencimento', '<', now()->toDateString())
                    ->sum('valor');

                return [
                    'periodo' => $period['periodo'],
                    'kpis' => [
                        'os_abertas' => $osAbertas,
                        'os_em_producao' => $osEmProducao,
                        'os_finalizadas_periodo' => $osFinalizadasPeriodo,
                        'faturamento_pago_periodo' => $faturamentoPagoPeriodo,
                        'valor_pendente' => $valorPendente,
                        'inadimplencia_total' => $inadimplenciaTotal,
                    ],
                ];
            }
        );

        return response()->json(['data' => $data]);
    }

    public function operacional(Request $request): JsonResponse
    {
        $empresaId = (int) ($request->attributes->get('empresa_id') ?? 0);
        if ($empresaId <= 0) {
            return response()->json(['message' => 'Empresa não informada.'], 400);
        }

        $period = $this->periodFromRequest($request);
        $ttl = 60;

        $data = Cache::remember(
            $this->cacheKey($empresaId, 'operacional', $period['periodo']),
            $ttl,
            function () use ($empresaId, $period) {
                $inicio = $period['inicio'];
                $fim = $period['fim'];

                // OS por status (colunas do Kanban)
                $rows = OrdemServico::query()
                    ->selectRaw('status_atual, COUNT(*) as total')
                    ->where('empresa_id', $empresaId)
                    ->groupBy('status_atual')
                    ->get();

                $porStatus = [
                    'aberta' => 0,
                    'em_producao' => 0,
                    'aguardando_pagamento' => 0,
                    'finalizada' => 0,
                    'cancelada' => 0,
                ];

                foreach ($rows as $r) {
                    $canon = $this->normalizeStatus((string) $r->status_atual);
                    if (isset($porStatus[$canon])) {
                        $porStatus[$canon] += (int) $r->total;
                    }
                }

                // Tempo médio em produção (em_producao -> aguardando_pagamento/finalizada), para OS que finalizaram no período
                $prodRows = DB::table('os_historico')
                    ->selectRaw("ordem_servico_id,
                        MIN(CASE WHEN status_novo = 'em_producao' THEN created_at END) as iniciou_producao,
                        MIN(CASE WHEN status_novo IN ('aguardando_pagamento','finalizada') THEN created_at END) as saiu_producao,
                        MIN(CASE WHEN status_novo = 'finalizada' THEN created_at END) as finalizada_at")
                    ->where('empresa_id', $empresaId)
                    ->groupBy('ordem_servico_id')
                    ->havingNotNull('iniciou_producao')
                    ->havingNotNull('saiu_producao')
                    ->get();

                $sumMinutes = 0;
                $count = 0;

                foreach ($prodRows as $r) {
                    if (! $r->finalizada_at) {
                        continue;
                    }

                    $finalizadaAt = Carbon::parse($r->finalizada_at);
                    if ($finalizadaAt->lt($inicio) || $finalizadaAt->gt($fim)) {
                        continue;
                    }

                    $ini = Carbon::parse($r->iniciou_producao);
                    $fimProd = Carbon::parse($r->saiu_producao);
                    $diff = $ini->diffInMinutes($fimProd, false);
                    if ($diff >= 0) {
                        $sumMinutes += $diff;
                        $count++;
                    }
                }

                $tempoMedioProducaoMin = $count > 0 ? (int) round($sumMinutes / $count) : 0;

                // Paradas por coluna: calcula "idle" pela última mudança em os_historico
                $idleRows = DB::table('ordens_servico as os')
                    ->leftJoin('os_historico as h', function ($join) use ($empresaId) {
                        $join->on('h.ordem_servico_id', '=', 'os.id')
                            ->where('h.empresa_id', '=', $empresaId);
                    })
                    ->selectRaw('os.id, os.numero, os.status_atual, COALESCE(MAX(h.created_at), os.updated_at) as last_move_at')
                    ->where('os.empresa_id', $empresaId)
                    ->groupBy('os.id', 'os.numero', 'os.status_atual', 'os.updated_at')
                    ->get();

                $paradasPorColuna = [
                    'aberta' => 0,
                    'em_producao' => 0,
                    'aguardando_pagamento' => 0,
                    'finalizada' => 0,
                    'cancelada' => 0,
                ];

                $gargaloOsMaisAntiga = null;
                $maxDias = -1;

                foreach ($idleRows as $r) {
                    $canon = $this->normalizeStatus((string) $r->status_atual);
                    if (! isset($paradasPorColuna[$canon])) {
                        continue;
                    }

                    // Considera "parada" para as colunas operacionais (não finais)
                    if (in_array($canon, ['aberta', 'em_producao', 'aguardando_pagamento'], true)) {
                        $paradasPorColuna[$canon]++;

                        $lastMove = $r->last_move_at ? Carbon::parse($r->last_move_at) : null;
                        if ($lastMove) {
                            $dias = $lastMove->diffInDays(now());
                            if ($dias > $maxDias) {
                                $maxDias = $dias;
                                $gargaloOsMaisAntiga = [
                                    'id' => (int) $r->id,
                                    'numero' => (string) $r->numero,
                                    'status' => $canon,
                                    'dias_parada' => $dias,
                                ];
                            }
                        }
                    }
                }

                // Coluna com mais OS (considera as colunas operacionais)
                $colunaMais = null;
                $colunaMaisCount = -1;
                foreach (['aberta', 'em_producao', 'aguardando_pagamento'] as $c) {
                    $cnt = (int) ($porStatus[$c] ?? 0);
                    if ($cnt > $colunaMaisCount) {
                        $colunaMaisCount = $cnt;
                        $colunaMais = $c;
                    }
                }

                return [
                    'periodo' => $period['periodo'],
                    'os_por_status' => $porStatus,
                    'tempo_medio_producao_min' => $tempoMedioProducaoMin,
                    'os_paradas_por_coluna' => $paradasPorColuna,
                    'gargalos' => [
                        'coluna_com_mais_os' => $colunaMais,
                        'os_parada_mais_tempo' => $gargaloOsMaisAntiga,
                    ],
                ];
            }
        );

        return response()->json(['data' => $data]);
    }

    public function financeiro(Request $request): JsonResponse
    {
        $empresaId = (int) ($request->attributes->get('empresa_id') ?? 0);
        if ($empresaId <= 0) {
            return response()->json(['message' => 'Empresa não informada.'], 400);
        }

        $period = $this->periodFromRequest($request);
        $ttl = 60;

        $data = Cache::remember(
            $this->cacheKey($empresaId, 'financeiro', $period['periodo']),
            $ttl,
            function () use ($empresaId, $period) {
                $inicio = $period['inicio'];
                $fim = $period['fim'];

                $iniDate = $inicio->toDateString();
                $fimDate = $fim->toDateString();

                $totalPago = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pago')
                    ->whereBetween('data_pagamento', [$iniDate, $fimDate])
                    ->sum('valor');

                $totalPendente = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pendente')
                    ->whereBetween('data_vencimento', [$iniDate, $fimDate])
                    ->sum('valor');

                $totalCancelado = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'cancelado')
                    ->whereBetween('data_vencimento', [$iniDate, $fimDate])
                    ->sum('valor');

                $osCountPago = (int) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pago')
                    ->whereBetween('data_pagamento', [$iniDate, $fimDate])
                    ->whereNotNull('ordem_servico_id')
                    ->distinct('ordem_servico_id')
                    ->count('ordem_servico_id');

                $ticketMedio = $osCountPago > 0 ? (float) ($totalPago / $osCountPago) : 0.0;

                // Tendência: período atual vs período anterior
                $antIni = $period['anterior_inicio']->toDateString();
                $antFim = $period['anterior_fim']->toDateString();

                $totalPagoAnt = (float) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pago')
                    ->whereBetween('data_pagamento', [$antIni, $antFim])
                    ->sum('valor');

                $osCountPagoAnt = (int) FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pago')
                    ->whereBetween('data_pagamento', [$antIni, $antFim])
                    ->whereNotNull('ordem_servico_id')
                    ->distinct('ordem_servico_id')
                    ->count('ordem_servico_id');

                $ticketMedioAnt = $osCountPagoAnt > 0 ? (float) ($totalPagoAnt / $osCountPagoAnt) : 0.0;

                return [
                    'periodo' => $period['periodo'],
                    'totais' => [
                        'pago' => $totalPago,
                        'pendente' => $totalPendente,
                        'cancelado' => $totalCancelado,
                        'ticket_medio_os' => $ticketMedio,
                    ],
                    'tendencia' => [
                        'faturado_pago' => $this->trend($totalPago, $totalPagoAnt),
                        'ticket_medio_os' => $this->trend($ticketMedio, $ticketMedioAnt),
                    ],
                ];
            }
        );

        return response()->json(['data' => $data]);
    }
}
