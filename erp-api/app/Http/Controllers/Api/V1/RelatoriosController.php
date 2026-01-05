<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RelatoriosController extends Controller
{
    private function parseDate(?string $value, string $field): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            abort(response()->json(['message' => "$field inválida"], Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }

    private function empresaId(Request $request): int
    {
        if ($request->has('empresa_id')) {
            abort(response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            abort(response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST));
        }

        return (int) $empresaId;
    }

    public function ordensServico(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $dataInicio = $this->parseDate($request->query('data_inicio'), 'data_inicio');
        $dataFim = $this->parseDate($request->query('data_fim'), 'data_fim');
        $clienteId = $request->query('cliente_id');
        $status = $request->query('status');

        $finalizacoes = DB::table('os_historico')
            ->select([
                'empresa_id',
                'ordem_servico_id',
                DB::raw('MAX(created_at) as data_finalizacao'),
            ])
            ->where('status_novo', 'finalizada')
            ->groupBy('empresa_id', 'ordem_servico_id');

        $query = DB::table('ordens_servico as os')
            ->join('clientes as c', function ($j) {
                $j->on('c.id', '=', 'os.cliente_id');
            })
            ->leftJoinSub($finalizacoes, 'fin', function ($j) {
                $j->on('fin.empresa_id', '=', 'os.empresa_id');
                $j->on('fin.ordem_servico_id', '=', 'os.id');
            })
            ->where('os.empresa_id', $empresaId)
            ->select([
                'os.id',
                'os.numero as numero_os',
                'os.status_atual as status',
                'os.valor_total',
                'os.created_at',
                'fin.data_finalizacao',
                'c.id as cliente_id',
                'c.nome as cliente_nome',
            ]);

        if ($dataInicio) {
            $query->whereDate('os.created_at', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('os.created_at', '<=', $dataFim);
        }

        if ($clienteId) {
            $query->where('os.cliente_id', (int) $clienteId);
        }

        if ($status) {
            $status = (string) $status;
            if ($status === 'aberta') {
                $query->whereIn('os.status_atual', ['aberta', 'criada']);
            } else {
                $query->where('os.status_atual', $status);
            }
        }

        $rows = $query->orderByDesc('os.created_at')->get();

        $data = $rows->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'numero_os' => $r->numero_os,
                'cliente' => [
                    'id' => (int) $r->cliente_id,
                    'nome' => $r->cliente_nome,
                ],
                'status' => $r->status === 'criada' ? 'aberta' : $r->status,
                'valor_total' => (float) $r->valor_total,
                'data_criacao' => $r->created_at,
                'data_finalizacao' => $r->data_finalizacao,
            ];
        });

        $totais = [
            'quantidade' => $data->count(),
            'valor_total' => (float) $data->sum('valor_total'),
        ];

        return response()->json(['data' => $data, 'totais' => $totais], Response::HTTP_OK);
    }

    public function producao(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $dataInicio = $this->parseDate($request->query('data_inicio'), 'data_inicio');
        $dataFim = $this->parseDate($request->query('data_fim'), 'data_fim');

        $finalizacoes = DB::table('os_historico')
            ->select([
                'empresa_id',
                'ordem_servico_id',
                DB::raw('MAX(created_at) as data_finalizacao'),
            ])
            ->where('status_novo', 'finalizada')
            ->groupBy('empresa_id', 'ordem_servico_id');

        $query = DB::table('ordens_servico as os')
            ->joinSub($finalizacoes, 'fin', function ($j) {
                $j->on('fin.empresa_id', '=', 'os.empresa_id');
                $j->on('fin.ordem_servico_id', '=', 'os.id');
            })
            ->join('os_itens as it', function ($j) {
                $j->on('it.ordem_servico_id', '=', 'os.id');
                $j->on('it.empresa_id', '=', 'os.empresa_id');
            })
            ->join('produtos as p', function ($j) {
                $j->on('p.id', '=', 'it.produto_id');
                $j->on('p.empresa_id', '=', 'os.empresa_id');
            })
            ->where('os.empresa_id', $empresaId)
            ->where('os.status_atual', 'finalizada')
            ->where('it.status', 'ativo')
            ->select([
                'os.id as ordem_servico_id',
                'os.numero as numero_os',
                'p.id as produto_id',
                'p.nome as produto_nome',
                DB::raw("SUM(CASE COALESCE(p.forma_calculo, p.tipo_medida) WHEN 'metro_linear' THEN COALESCE(it.comprimento, it.quantidade) WHEN 'metro_quadrado' THEN (COALESCE(it.largura, 0) * COALESCE(it.altura, 0)) ELSE it.quantidade END) as quantidade_utilizada"),
                'fin.data_finalizacao',
            ])
            ->groupBy('os.id', 'os.numero', 'p.id', 'p.nome', 'fin.data_finalizacao');

        if ($dataInicio) {
            $query->whereDate('fin.data_finalizacao', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('fin.data_finalizacao', '<=', $dataFim);
        }

        $rows = $query->orderByDesc('fin.data_finalizacao')->get();

        $data = $rows->map(function ($r) {
            return [
                'produto' => [
                    'id' => (int) $r->produto_id,
                    'nome' => $r->produto_nome,
                ],
                'quantidade_utilizada' => (float) $r->quantidade_utilizada,
                'origem' => [
                    'tipo' => 'os',
                    'ordem_servico_id' => (int) $r->ordem_servico_id,
                    'numero_os' => $r->numero_os,
                    'data_finalizacao' => $r->data_finalizacao,
                ],
            ];
        });

        $totais = [
            'quantidade_total_utilizada' => (float) $data->sum('quantidade_utilizada'),
            'linhas' => $data->count(),
        ];

        return response()->json(['data' => $data, 'totais' => $totais], Response::HTTP_OK);
    }

    public function produtosMaisUsados(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $dataInicio = $this->parseDate($request->query('data_inicio'), 'data_inicio');
        $dataFim = $this->parseDate($request->query('data_fim'), 'data_fim');

        $finalizacoes = DB::table('os_historico')
            ->select([
                'empresa_id',
                'ordem_servico_id',
                DB::raw('MAX(created_at) as data_finalizacao'),
            ])
            ->where('status_novo', 'finalizada')
            ->groupBy('empresa_id', 'ordem_servico_id');

        $query = DB::table('ordens_servico as os')
            ->joinSub($finalizacoes, 'fin', function ($j) {
                $j->on('fin.empresa_id', '=', 'os.empresa_id');
                $j->on('fin.ordem_servico_id', '=', 'os.id');
            })
            ->join('os_itens as it', function ($j) {
                $j->on('it.ordem_servico_id', '=', 'os.id');
                $j->on('it.empresa_id', '=', 'os.empresa_id');
            })
            ->join('produtos as p', function ($j) {
                $j->on('p.id', '=', 'it.produto_id');
                $j->on('p.empresa_id', '=', 'os.empresa_id');
            })
            ->where('os.empresa_id', $empresaId)
            ->where('os.status_atual', 'finalizada')
            ->where('it.status', 'ativo')
            ->select([
                'p.id as produto_id',
                'p.nome as produto_nome',
                DB::raw("SUM(CASE COALESCE(p.forma_calculo, p.tipo_medida) WHEN 'metro_linear' THEN COALESCE(it.comprimento, it.quantidade) WHEN 'metro_quadrado' THEN (COALESCE(it.largura, 0) * COALESCE(it.altura, 0)) ELSE it.quantidade END) as quantidade_total_utilizada"),
            ])
            ->groupBy('p.id', 'p.nome');

        if ($dataInicio) {
            $query->whereDate('fin.data_finalizacao', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('fin.data_finalizacao', '<=', $dataFim);
        }

        $rows = $query->orderByDesc('quantidade_total_utilizada')->get();

        $data = $rows->map(function ($r) {
            return [
                'produto' => [
                    'id' => (int) $r->produto_id,
                    'nome' => $r->produto_nome,
                ],
                'quantidade_total_utilizada' => (float) $r->quantidade_total_utilizada,
            ];
        });

        $totais = [
            'quantidade_total_utilizada' => (float) $data->sum('quantidade_total_utilizada'),
            'produtos' => $data->count(),
        ];

        return response()->json(['data' => $data, 'totais' => $totais], Response::HTTP_OK);
    }

    public function financeiro(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $dataInicio = $this->parseDate($request->query('data_inicio'), 'data_inicio');
        $dataFim = $this->parseDate($request->query('data_fim'), 'data_fim');
        $clienteId = $request->query('cliente_id');
        $status = $request->query('status');

        $query = DB::table('financeiro_lancamentos as f')
            ->join('clientes as c', function ($j) {
                $j->on('c.id', '=', 'f.cliente_id');
                $j->on('c.empresa_id', '=', 'f.empresa_id');
            })
            ->join('ordens_servico as os', function ($j) {
                $j->on('os.id', '=', 'f.ordem_servico_id');
                $j->on('os.empresa_id', '=', 'f.empresa_id');
            })
            ->where('f.empresa_id', $empresaId)
            ->where('f.tipo', 'receber')
            ->select([
                'f.id',
                'f.valor',
                'f.status',
                'f.data_vencimento',
                'c.id as cliente_id',
                'c.nome as cliente_nome',
                'os.id as ordem_servico_id',
                'os.numero as numero_os',
            ]);

        if ($dataInicio) {
            $query->whereDate('f.data_vencimento', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('f.data_vencimento', '<=', $dataFim);
        }
        if ($clienteId) {
            $query->where('f.cliente_id', (int) $clienteId);
        }
        if ($status) {
            $query->where('f.status', (string) $status);
        }

        $rows = $query->orderBy('f.data_vencimento', 'asc')->get();

        $data = $rows->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'cliente' => [
                    'id' => (int) $r->cliente_id,
                    'nome' => $r->cliente_nome,
                ],
                'ordem_servico' => [
                    'id' => (int) $r->ordem_servico_id,
                    'numero_os' => $r->numero_os,
                ],
                'valor' => (float) $r->valor,
                'vencimento' => $r->data_vencimento,
                'status' => $r->status,
            ];
        });

        $totais = [
            'quantidade' => $data->count(),
            'valor_total' => (float) $data->sum('valor'),
            'total_pago' => (float) $data->where('status', 'pago')->sum('valor'),
            'total_pendente' => (float) $data->where('status', 'pendente')->sum('valor'),
            'total_cancelado' => (float) $data->where('status', 'cancelado')->sum('valor'),
        ];

        return response()->json(['data' => $data, 'totais' => $totais], Response::HTTP_OK);
    }

    public function faturamento(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $dataInicio = $this->parseDate($request->query('data_inicio'), 'data_inicio');
        $dataFim = $this->parseDate($request->query('data_fim'), 'data_fim');

        $query = DB::table('financeiro_lancamentos as f')
            ->where('f.empresa_id', $empresaId)
            ->where('f.tipo', 'receber');

        if ($dataInicio) {
            $query->whereDate('f.data_vencimento', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('f.data_vencimento', '<=', $dataFim);
        }

        $rows = $query
            ->select([
                'f.status',
                DB::raw('SUM(f.valor) as total'),
            ])
            ->groupBy('f.status')
            ->get();

        $map = [
            'pago' => 0.0,
            'pendente' => 0.0,
            'cancelado' => 0.0,
        ];

        foreach ($rows as $r) {
            $s = (string) $r->status;
            if (array_key_exists($s, $map)) {
                $map[$s] = (float) $r->total;
            }
        }

        return response()->json([
            'data' => [
                'total_faturado' => $map['pago'],
                'total_pendente' => $map['pendente'],
                'total_cancelado' => $map['cancelado'],
            ],
        ], Response::HTTP_OK);
    }

    public function inadimplencia(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $clienteId = $request->query('cliente_id');
        $hoje = Carbon::today();

        $query = DB::table('financeiro_lancamentos as f')
            ->join('clientes as c', function ($j) {
                $j->on('c.id', '=', 'f.cliente_id');
                $j->on('c.empresa_id', '=', 'f.empresa_id');
            })
            ->join('ordens_servico as os', function ($j) {
                $j->on('os.id', '=', 'f.ordem_servico_id');
                $j->on('os.empresa_id', '=', 'f.empresa_id');
            })
            ->where('f.empresa_id', $empresaId)
            ->where('f.tipo', 'receber')
            ->where('f.status', 'pendente')
            ->whereDate('f.data_vencimento', '<', $hoje->toDateString())
            ->select([
                'f.id',
                'f.valor',
                'f.data_vencimento',
                'c.id as cliente_id',
                'c.nome as cliente_nome',
                'os.id as ordem_servico_id',
                'os.numero as numero_os',
            ]);

        if ($clienteId) {
            $query->where('f.cliente_id', (int) $clienteId);
        }

        $rows = $query->orderBy('f.data_vencimento', 'asc')->get();

        $data = $rows->map(function ($r) use ($hoje) {
            $v = Carbon::parse($r->data_vencimento);
            $dias = $v->diffInDays($hoje);

            return [
                'id' => (int) $r->id,
                'cliente' => [
                    'id' => (int) $r->cliente_id,
                    'nome' => $r->cliente_nome,
                ],
                'ordem_servico' => [
                    'id' => (int) $r->ordem_servico_id,
                    'numero_os' => $r->numero_os,
                ],
                'valor_pendente' => (float) $r->valor,
                'vencimento' => $r->data_vencimento,
                'dias_em_atraso' => (int) $dias,
            ];
        });

        $totais = [
            'quantidade' => $data->count(),
            'valor_total_pendente' => (float) $data->sum('valor_pendente'),
        ];

        return response()->json(['data' => $data, 'totais' => $totais], Response::HTTP_OK);
    }
}
