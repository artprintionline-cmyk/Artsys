<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\OrdemServico;
use App\Models\Pagamento;
use App\Models\FinanceiroLancamento;
use App\Models\WhatsAppMensagem;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrdemServicoWhatsAppController extends Controller
{
    public function historico(Request $request, int $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $os = OrdemServico::with(['cliente'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $os) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $msgs = WhatsAppMensagem::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($id) {
                $q->where('ordem_servico_id', $id)
                    ->orWhere(function ($q2) use ($id) {
                        $q2->whereNull('ordem_servico_id')
                            ->where('contexto', 'os')
                            ->where('referencia_id', $id);
                    });
            })
            ->orderBy('created_at', 'asc')
            ->limit(200)
            ->get();

        $data = $msgs->map(function (WhatsAppMensagem $m) {
            return [
                'id' => $m->id,
                'direcao' => $m->direcao ?? null,
                'tipo' => $m->tipo ?? null,
                'mensagem' => $m->mensagem,
                'status' => $m->status,
                'created_at' => (string) $m->created_at,
            ];
        });

        return response()->json([
            'data' => [
                'os' => [
                    'id' => (int) $os->id,
                    'numero_os' => (string) $os->numero,
                    'status' => (string) $os->status_atual,
                ],
                'cliente' => $os->cliente ? [
                    'id' => (int) $os->cliente->id,
                    'nome' => (string) $os->cliente->nome,
                    'telefone' => (string) ($os->cliente->telefone ?? ''),
                ] : null,
                'mensagens' => $data,
            ],
        ], Response::HTTP_OK);
    }

    public function enviar(Request $request, int $id, AuditoriaService $auditoria): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'tipo' => 'required|string|in:texto,pix_qr',
            'mensagem' => 'sometimes|nullable|string|max:2000',
        ]);

        $os = OrdemServico::with(['cliente'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $os) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], Response::HTTP_NOT_FOUND);
        }

        if (! $os->cliente || empty($os->cliente->telefone)) {
            return response()->json(['message' => 'Cliente sem telefone para WhatsApp.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tipo = (string) $validated['tipo'];

        $mensagem = '';
        $tipoDb = 'text';

        if ($tipo === 'texto') {
            $mensagem = isset($validated['mensagem']) ? (string) $validated['mensagem'] : '';
            if (trim($mensagem) === '') {
                return response()->json(['message' => 'mensagem é obrigatória para tipo=texto'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $tipoDb = 'text';
        }

        if ($tipo === 'pix_qr') {
            $lancamento = FinanceiroLancamento::query()
                ->where('empresa_id', $empresaId)
                ->where('ordem_servico_id', $os->id)
                ->orderByDesc('id')
                ->first();

            if (! $lancamento) {
                return response()->json(['message' => 'Não existe lançamento financeiro para esta OS.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $pagamento = Pagamento::query()
                ->where('empresa_id', $empresaId)
                ->where('financeiro_lancamento_id', $lancamento->id)
                ->where('metodo', 'pix')
                ->orderByDesc('id')
                ->first();

            if (! $pagamento || empty($pagamento->qr_code_text)) {
                return response()->json(['message' => 'PIX ainda não foi gerado para esta OS.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $valor = number_format((float) $lancamento->valor, 2, ',', '.');
            $mensagem = "PIX gerado para OS {$os->numero} no valor de R$ {$valor}.\n\n";
            $mensagem .= "Copia e cola:\n{$pagamento->qr_code_text}";
            $tipoDb = 'pix_qr';
        }

        dispatch(new SendWhatsAppMessageJob(
            (int) $empresaId,
            $os->cliente_id ? (int) $os->cliente_id : null,
            (string) $os->cliente->telefone,
            $mensagem,
            'os',
            (int) $os->id,
            (int) $os->id,
            $tipoDb
        ));

        $auditoria->log($request, 'whatsapp_send', 'os', (int) $os->id, null, [
            'tipo' => $tipo,
            'numero' => (string) $os->cliente->telefone,
        ]);

        return response()->json(['ok' => true], Response::HTTP_ACCEPTED);
    }
}
