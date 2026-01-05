<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\PagamentoConfirmadoEvent;
use App\Events\OsStatusMovidaEvent;
use App\Models\Empresa;
use App\Models\FinanceiroLancamento;
use App\Models\Pagamento;
use App\Models\OsHistorico;
use App\Models\OrdemServico;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MercadoPagoController extends Controller
{
    public function gerarPix(Request $request, MercadoPagoService $service): JsonResponse
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

        $empresa = Empresa::find($empresaId);
        if (! $empresa) {
            return response()->json(['message' => 'Empresa não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $lancamento = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $empresaId)
            ->where('id', $validated['financeiro_lancamento_id'])
            ->first();

        if (! $lancamento) {
            return response()->json(['message' => 'Lançamento não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($lancamento->status === 'cancelado') {
            return response()->json(['message' => 'Lançamento cancelado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cliente = $lancamento->cliente;
        $payerEmail = $cliente && ! empty($cliente->email) ? (string) $cliente->email : '';
        if ($payerEmail === '') {
            return response()->json(['message' => 'Cliente sem email. Informe um email no cadastro do cliente para gerar PIX.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Idempotência: se já existe um PIX pendente/pago para este lançamento, retorna o mais recente.
        $existing = Pagamento::where('empresa_id', $empresaId)
            ->where('financeiro_lancamento_id', $lancamento->id)
            ->where('metodo', 'pix')
            ->orderByDesc('id')
            ->first();

        if ($existing && in_array($existing->status, ['pendente', 'pago'], true) && $existing->qr_code_base64) {
            return response()->json([
                'data' => [
                    'payment_id' => $existing->payment_id,
                    'status' => $existing->status,
                    'qr_code_base64' => $existing->qr_code_base64,
                    'qr_code_text' => $existing->qr_code_text,
                ],
            ], Response::HTTP_OK);
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $notificationUrl = $baseUrl . '/api/v1/mercado-pago/webhook?empresa_id=' . $empresaId;

        $descricao = (string) ($lancamento->descricao ?: ('Lançamento #' . $lancamento->id));

        try {
            $mp = $service->criarPagamentoPix(
                $empresa,
                (float) $lancamento->valor,
                $descricao,
                $payerEmail,
                $notificationUrl,
                [
                    'empresa_id' => $empresaId,
                    'financeiro_lancamento_id' => $lancamento->id,
                    'ordem_servico_id' => $lancamento->ordem_servico_id,
                ]
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $status = $this->mapMpStatusToLocal($mp['status'] ?? '');

        try {
            $pagamento = Pagamento::create([
                'empresa_id' => $empresaId,
                'financeiro_lancamento_id' => $lancamento->id,
                'metodo' => 'pix',
                'status' => $status,
                'payment_id' => $mp['payment_id'] ?? null,
                'qr_code_base64' => $mp['qr_code_base64'] ?? null,
                'qr_code_text' => $mp['qr_code_text'] ?? null,
                'payload' => $mp['raw'] ?? null,
            ]);
        } catch (QueryException $e) {
            // Anti-duplicidade: payment_id é unique. Se houve corrida, busca o registro existente.
            $paymentId = (string) ($mp['payment_id'] ?? '');
            if ($paymentId !== '') {
                $existingByPaymentId = Pagamento::where('empresa_id', $empresaId)
                    ->where('payment_id', $paymentId)
                    ->first();

                if ($existingByPaymentId) {
                    $pagamento = $existingByPaymentId;
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        // Best-effort: envia o "copia e cola" por WhatsApp quando existir telefone do cliente.
        try {
            if ($cliente && ! empty($cliente->telefone) && ! empty($pagamento->qr_code_text)) {
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
                    $cliente ? (int) $cliente->id : null,
                    (string) $cliente->telefone,
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
            }
        } catch (\Throwable $e) {
            // não bloquear geração do PIX
        }

        return response()->json([
            'data' => [
                'payment_id' => $pagamento->payment_id,
                'status' => $pagamento->status,
                'qr_code_base64' => $pagamento->qr_code_base64,
                'qr_code_text' => $pagamento->qr_code_text,
            ],
        ], Response::HTTP_CREATED);
    }

    public function webhook(Request $request, MercadoPagoService $service): JsonResponse
    {
        // Identificação do tenant via query param (URL específica por empresa)
        $empresaId = $request->query('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'empresa_id é obrigatório no webhook'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Empresa|null $empresa */
        $empresa = Empresa::find($empresaId);
        if (! $empresa) {
            return response()->json(['message' => 'Empresa não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $secret = (string) ($empresa->mercado_pago_webhook_secret ?? '');
        if ($secret === '') {
            $this->logWebhook($empresa->id, null, false, null, null, null, Response::HTTP_UNAUTHORIZED, 'invalido', 'Webhook secret não configurado', $request->all(), null, $request);
            return response()->json(['message' => 'Webhook secret não configurado para esta empresa.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->validateWebhookSignature($request, $secret)) {
            $this->logWebhook($empresa->id, null, false, null, null, null, Response::HTTP_UNAUTHORIZED, 'invalido', 'Assinatura inválida', $request->all(), null, $request);
            return response()->json(['message' => 'Assinatura inválida'], Response::HTTP_UNAUTHORIZED);
        }

        $paymentId = $this->extractPaymentId($request);
        if (! $paymentId) {
            $this->logWebhook($empresa->id, null, true, null, null, null, Response::HTTP_BAD_REQUEST, 'invalido', 'payment id não informado', $request->all(), null, $request);
            return response()->json(['message' => 'payment id não informado'], Response::HTTP_BAD_REQUEST);
        }

        // Confere o status diretamente na API (nunca confiar apenas no payload)
        try {
            $mpPayment = $service->consultarPagamento($empresa, (string) $paymentId);
        } catch (\Throwable $e) {
            $this->logWebhook($empresa->id, (string) $paymentId, true, null, null, null, Response::HTTP_BAD_GATEWAY, 'erro', $e->getMessage(), $request->all(), null, $request);
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $mpStatus = (string) ($mpPayment['status'] ?? '');
        $localStatus = $this->mapMpStatusToLocal($mpStatus);

        // Determina o lançamento: prioriza metadata, fallback por pagamento registrado.
        $meta = $mpPayment['metadata'] ?? null;
        $financeiroLancamentoId = is_array($meta) ? ($meta['financeiro_lancamento_id'] ?? null) : null;
        $metaEmpresaId = is_array($meta) ? ($meta['empresa_id'] ?? null) : null;

        if ($metaEmpresaId !== null && (string) $metaEmpresaId !== (string) $empresa->id) {
            $this->logWebhook($empresa->id, (string) $paymentId, true, $mpStatus, $localStatus, null, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalido', 'metadata empresa_id divergente', $request->all(), $mpPayment, $request);
            return response()->json(['message' => 'Pagamento inválido para esta empresa'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var Pagamento|null $pagamento */
        $pagamento = Pagamento::where('empresa_id', $empresa->id)
            ->where('payment_id', (string) $paymentId)
            ->orderByDesc('id')
            ->first();

        if (! $pagamento) {
            if (! $financeiroLancamentoId) {
                $this->logWebhook($empresa->id, (string) $paymentId, true, $mpStatus, $localStatus, null, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalido', 'Pagamento não vinculado a um lançamento', $request->all(), $mpPayment, $request);
                return response()->json(['message' => 'Pagamento não vinculado a um lançamento'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            try {
                $pagamento = Pagamento::create([
                    'empresa_id' => $empresa->id,
                    'financeiro_lancamento_id' => (int) $financeiroLancamentoId,
                    'metodo' => 'pix',
                    'status' => $localStatus,
                    'payment_id' => (string) $paymentId,
                    'qr_code_base64' => null,
                    'qr_code_text' => null,
                    'payload' => $mpPayment,
                ]);
            } catch (QueryException $e) {
                $pagamento = Pagamento::where('empresa_id', $empresa->id)
                    ->where('payment_id', (string) $paymentId)
                    ->orderByDesc('id')
                    ->first();

                if (! $pagamento) {
                    throw $e;
                }
            }
        } else {
            // Nunca regride pago -> pendente/cancelado
            if ($pagamento->status !== 'pago' || $localStatus === 'pago') {
                $pagamento->status = $localStatus;
            }
            $pagamento->payload = $mpPayment;
            $pagamento->save();
        }

        // Carrega o lançamento e valida escopo
        $lancamento = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $empresa->id)
            ->where('id', $pagamento->financeiro_lancamento_id)
            ->first();

        if (! $lancamento) {
            $this->logWebhook($empresa->id, (string) $paymentId, true, $mpStatus, $localStatus, $pagamento->financeiro_lancamento_id, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalido', 'Lançamento não encontrado', $request->all(), $mpPayment, $request);
            return response()->json(['message' => 'Lançamento não encontrado'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validação de valor: não confirma pagamento com divergência (tolerância 1 centavo)
        $txAmount = $mpPayment['transaction_amount'] ?? null;
        if ($localStatus === 'pago' && $txAmount !== null) {
            $expectedCents = (int) round(((float) $lancamento->valor) * 100);
            $paidCents = (int) round(((float) $txAmount) * 100);
            if ($expectedCents !== $paidCents) {
                $this->logWebhook($empresa->id, (string) $paymentId, true, $mpStatus, $localStatus, $lancamento->id, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalido', 'Valor do pagamento divergente', $request->all(), $mpPayment, $request);
                return response()->json(['message' => 'Valor do pagamento divergente'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Atualiza financeiro (idempotente)
        if ($localStatus === 'pago') {
            if ($lancamento->status !== 'pago') {
                $dateApproved = $mpPayment['date_approved'] ?? null;
                $dataPagamento = now()->toDateString();
                if (is_string($dateApproved) && $dateApproved !== '') {
                    try {
                        $dataPagamento = Carbon::parse($dateApproved)->toDateString();
                    } catch (\Throwable $e) {
                        // fallback para now
                    }
                }

                $lancamento->status = 'pago';
                $lancamento->data_pagamento = $dataPagamento;
                $lancamento->save();

                // Automações por evento (best-effort)
                try {
                    event(new PagamentoConfirmadoEvent(
                        (int) $empresa->id,
                        (int) $lancamento->id,
                        $lancamento->ordem_servico_id ? (int) $lancamento->ordem_servico_id : null,
                        $lancamento->cliente_id ? (int) $lancamento->cliente_id : null,
                        'mercadopago'
                    ));
                } catch (\Throwable $e) {
                    // não bloquear webhook
                }

                $this->finalizarOrdemServico($empresa->id, $lancamento->ordem_servico_id);
            }
        } elseif ($localStatus === 'cancelado') {
            // Nunca regride pago
            if ($lancamento->status !== 'pago') {
                $lancamento->status = 'cancelado';
                $lancamento->data_pagamento = null;
                $lancamento->save();
            }
        } else {
            // pendente: nunca regride pago
            if ($lancamento->status !== 'pago' && $lancamento->status !== 'pendente') {
                $lancamento->status = 'pendente';
                $lancamento->data_pagamento = null;
                $lancamento->save();
            }
        }

        $this->logWebhook($empresa->id, (string) $paymentId, true, $mpStatus, $localStatus, $lancamento->id, Response::HTTP_OK, 'ok', 'Processado', $request->all(), $mpPayment, $request);

        return response()->json(['ok' => true], Response::HTTP_OK);
    }

    private function logWebhook(
        ?int $empresaId,
        ?string $paymentId,
        bool $assinaturaOk,
        ?string $mpStatus,
        ?string $statusLocal,
        ?int $financeiroLancamentoId,
        ?int $httpStatus,
        ?string $resultado,
        ?string $mensagem,
        $requestPayload,
        $mpPayload,
        Request $request
    ): void {
        try {
            DB::table('mercado_pago_webhook_logs')->insert([
                'empresa_id' => $empresaId,
                'payment_id' => $paymentId,
                'x_request_id' => (string) $request->header('x-request-id', ''),
                'assinatura_ok' => $assinaturaOk,
                'mp_status' => $mpStatus,
                'status_local' => $statusLocal,
                'financeiro_lancamento_id' => $financeiroLancamentoId,
                'http_status' => $httpStatus,
                'resultado' => $resultado,
                'mensagem' => $mensagem,
                'request_payload' => $requestPayload ? json_encode($requestPayload) : null,
                'mp_payload' => $mpPayload ? json_encode($mpPayload) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // best-effort: não bloquear o webhook
        }
    }

    private function extractPaymentId(Request $request): ?string
    {
        // Mercadopago pode mandar via query como data[id]=123 (array), data_id=123 (php) ou via body data.id
        $q = $request->query('data.id');
        if ($q) {
            return (string) $q;
        }

        $q2 = $request->query('data_id');
        if ($q2) {
            return (string) $q2;
        }

        $q3 = $request->query('data');
        if (is_array($q3) && isset($q3['id'])) {
            return (string) $q3['id'];
        }

        $body = $request->all();
        if (isset($body['data']['id'])) {
            return (string) $body['data']['id'];
        }

        return null;
    }

    private function mapMpStatusToLocal(string $mpStatus): string
    {
        $s = strtolower(trim($mpStatus));
        if ($s === 'approved') return 'pago';
        if (in_array($s, ['cancelled', 'canceled', 'rejected', 'refunded', 'charged_back'], true)) {
            return 'cancelado';
        }
        return 'pendente';
    }

    private function validateWebhookSignature(Request $request, string $secret): bool
    {
        $xSignature = (string) $request->header('x-signature', '');
        $xRequestId = (string) $request->header('x-request-id', '');
        if ($xSignature === '' || $xRequestId === '') {
            return false;
        }

        $parts = explode(',', $xSignature);
        $ts = null;
        $hash = null;

        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            if ($kv[0] === 'ts') $ts = $kv[1];
            if ($kv[0] === 'v1') $hash = $kv[1];
        }

        if (! $ts || ! $hash) {
            return false;
        }

        // Tolerância de 10 minutos
        $now = now()->timestamp;
        $tsInt = (int) $ts;
        if ($tsInt <= 0 || abs($now - $tsInt) > 600) {
            return false;
        }

        $dataId = (string) ($request->query('data.id') ?? '');
        if ($dataId === '') {
            $dataId = (string) ($request->query('data_id') ?? '');
        }

        if ($dataId === '') {
            $data = $request->query('data');
            if (is_array($data) && isset($data['id'])) {
                $dataId = (string) $data['id'];
            }
        }

        if ($dataId === '') {
            $body = $request->all();
            if (is_array($body) && isset($body['data']['id'])) {
                $dataId = (string) $body['data']['id'];
            }
        }
        $manifestParts = [];
        if ($dataId !== '') {
            $manifestParts[] = 'id:' . strtolower($dataId);
        }
        if ($xRequestId !== '') {
            $manifestParts[] = 'request-id:' . $xRequestId;
        }
        if ($ts !== '') {
            $manifestParts[] = 'ts:' . $ts;
        }

        $manifest = implode(';', $manifestParts) . ';';
        $sha = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($sha, $hash);
    }

    private function finalizarOrdemServico(int $empresaId, ?int $ordemServicoId): void
    {
        if (! $ordemServicoId) {
            return;
        }

        /** @var OrdemServico|null $os */
        $os = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $ordemServicoId)
            ->first();

        if (! $os) {
            return;
        }

        if (in_array($os->status_atual, ['finalizada', 'cancelada'], true)) {
            return;
        }

        $statusAnterior = $os->status_atual;
        $os->status_atual = 'finalizada';
        $os->save();

        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $os->id,
            'usuario_id' => 0,
            'status_anterior' => (string) $statusAnterior,
            'status_novo' => 'finalizada',
        ]);

        try {
            event(new OsStatusMovidaEvent((int) $empresaId, (int) $os->id, (string) $statusAnterior, 'finalizada', 0));
        } catch (\Throwable $e) {
            // não bloquear
        }
    }
}
