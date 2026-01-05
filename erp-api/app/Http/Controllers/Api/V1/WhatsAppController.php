<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Cliente;
use App\Models\FinanceiroLancamento;
use App\Models\Pagamento;
use App\Models\WhatsAppMensagem;
use App\Services\WhatsAppSenderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppController extends Controller
{
    public function conversas(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        // Lista por número com última mensagem
        $rows = WhatsAppMensagem::query()
            ->selectRaw('numero, MAX(created_at) as last_at')
            ->where('empresa_id', $empresaId)
            ->groupBy('numero')
            ->orderByDesc('last_at')
            ->limit(200)
            ->get();

        $numeros = $rows->pluck('numero')->all();

        $lastByNumero = WhatsAppMensagem::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('numero', $numeros)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('numero')
            ->map(fn ($g) => $g->first());

        $data = [];
        foreach ($numeros as $numero) {
            /** @var WhatsAppMensagem|null $last */
            $last = $lastByNumero->get($numero);
            $cliente = null;

            if ($last && $last->cliente_id) {
                $cliente = Cliente::where('empresa_id', $empresaId)->where('id', $last->cliente_id)->first();
            }

            $data[] = [
                'numero' => $numero,
                'cliente' => $cliente ? ['id' => $cliente->id, 'nome' => $cliente->nome] : null,
                'ultima_mensagem' => $last ? $last->mensagem : null,
                'ultima_direcao' => $last ? $last->direcao : null,
                'ultima_em' => $last ? (string) $last->created_at : null,
            ];
        }

        return response()->json(['data' => $data], Response::HTTP_OK);
    }

    public function conversa(Request $request, string $numero): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $numeroNormalizado = $this->normalizeNumber($numero);

        $msgs = WhatsAppMensagem::query()
            ->where('empresa_id', $empresaId)
            ->where('numero', $numeroNormalizado)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

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

        return response()->json(['data' => $data], Response::HTTP_OK);
    }

    public function enviarMensagem(Request $request, string $numero): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'mensagem' => 'required|string|max:2000',
            'cliente_id' => 'nullable|integer',
        ]);

        $numeroNormalizado = $this->normalizeNumber($numero);

        $clienteId = isset($validated['cliente_id']) ? (int) $validated['cliente_id'] : null;
        if ($clienteId) {
            $exists = Cliente::where('empresa_id', $empresaId)->where('id', $clienteId)->exists();
            if (! $exists) {
                return response()->json(['message' => 'Cliente inválido.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        dispatch(new SendWhatsAppMessageJob(
            (int) $empresaId,
            $clienteId,
            $numeroNormalizado,
            (string) $validated['mensagem'],
            'conversa',
            null
        ));

        return response()->json(['ok' => true], Response::HTTP_ACCEPTED);
    }

    public function enviarPix(Request $request, WhatsAppSenderService $sender): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'financeiro_lancamento_id' => 'required|integer',
        ]);

        $lancamento = FinanceiroLancamento::with(['cliente'])
            ->where('empresa_id', $empresaId)
            ->where('id', $validated['financeiro_lancamento_id'])
            ->first();

        if (! $lancamento) {
            return response()->json(['message' => 'Lançamento não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (! $lancamento->cliente || empty($lancamento->cliente->telefone)) {
            return response()->json(['message' => 'Cliente sem telefone para WhatsApp.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pagamento = Pagamento::where('empresa_id', $empresaId)
            ->where('financeiro_lancamento_id', $lancamento->id)
            ->where('metodo', 'pix')
            ->orderByDesc('id')
            ->first();

        if (! $pagamento || empty($pagamento->qr_code_text)) {
            return response()->json(['message' => 'PIX ainda não foi gerado para este lançamento.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $valor = number_format((float) $lancamento->valor, 2, ',', '.');
        $osNumero = $lancamento->ordemServico ? ($lancamento->ordemServico->numero ?? $lancamento->ordemServico->numero_os ?? '') : '';

        $mensagem = "PIX gerado";
        if ($osNumero !== '') {
            $mensagem .= " para OS {$osNumero}";
        }
        $mensagem .= " no valor de R$ {$valor}.\n\n";
        $mensagem .= "Copia e cola:\n{$pagamento->qr_code_text}";

        dispatch(new SendWhatsAppMessageJob(
            (int) $empresaId,
            $lancamento->cliente_id,
            (string) $lancamento->cliente->telefone,
            $mensagem,
            'pix',
            (int) $lancamento->id
        ));

            // Controle de reenvios (best-effort)
            try {
                $pagamento->ultimo_envio_whatsapp_at = now();
                $pagamento->envios_whatsapp_count = (int) ($pagamento->envios_whatsapp_count ?? 0) + 1;
                $pagamento->save();
            } catch (\Throwable $e) {
                // não bloquear
            }

        return response()->json(['ok' => true], Response::HTTP_ACCEPTED);
    }

    private function normalizeNumber(string $numero): string
    {
        $digits = preg_replace('/\D+/', '', $numero) ?? '';
        $digits = ltrim($digits, '0');

        if ($digits !== '' && ! str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            return '55' . $digits;
        }

        return $digits;
    }
}
