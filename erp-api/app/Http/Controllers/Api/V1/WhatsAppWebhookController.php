<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\WhatsAppConfiguracao;
use App\Models\WhatsAppMensagem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $empresaId = $request->query('empresa_id');
        if (! $empresaId) {
            return response('empresa_id é obrigatório', Response::HTTP_BAD_REQUEST);
        }

        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $verifyToken = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($mode !== 'subscribe') {
            return response('invalid mode', Response::HTTP_BAD_REQUEST);
        }

        $config = WhatsAppConfiguracao::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderByDesc('id')
            ->first();

        if (! $config || empty($config->verify_token) || $verifyToken !== (string) $config->verify_token) {
            return response('invalid token', Response::HTTP_FORBIDDEN);
        }

        return response($challenge, Response::HTTP_OK);
    }

    public function webhook(Request $request): JsonResponse
    {
        $empresaId = $request->query('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'empresa_id é obrigatório'], Response::HTTP_BAD_REQUEST);
        }

        $config = WhatsAppConfiguracao::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderByDesc('id')
            ->first();

        if (! $config) {
            return response()->json(['message' => 'Configuração WhatsApp não encontrada'], Response::HTTP_NOT_FOUND);
        }

        if (! $this->validateSignature($request, (string) ($config->app_secret ?? ''))) {
            return response()->json(['message' => 'Assinatura inválida'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();

        // Mensagens recebidas
        $entries = $payload['entry'] ?? [];
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                if (! is_array($changes)) continue;

                foreach ($changes as $change) {
                    $value = $change['value'] ?? null;
                    if (! is_array($value)) continue;

                    // inbound messages
                    $messages = $value['messages'] ?? [];
                    if (is_array($messages)) {
                        foreach ($messages as $m) {
                            $from = isset($m['from']) ? (string) $m['from'] : '';
                            $textBody = isset($m['text']['body']) ? (string) $m['text']['body'] : '';
                            if ($from === '' || $textBody === '') continue;

                            $clienteId = $this->resolveClienteId((int) $empresaId, $from);

                            WhatsAppMensagem::create([
                                'empresa_id' => (int) $empresaId,
                                'cliente_id' => $clienteId,
                                'numero' => $this->normalizeNumber($from),
                                'mensagem' => $textBody,
                                'direcao' => 'entrada',
                                'tipo' => 'text',
                                'provider_message_id' => isset($m['id']) ? (string) $m['id'] : null,
                                'status' => 'recebido',
                                'contexto' => 'inbound',
                                'referencia_id' => null,
                                'payload' => $payload,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }

                    // delivery statuses
                    $statuses = $value['statuses'] ?? [];
                    if (is_array($statuses)) {
                        foreach ($statuses as $st) {
                            $id = isset($st['id']) ? (string) $st['id'] : '';
                            $status = isset($st['status']) ? (string) $st['status'] : '';
                            if ($id === '' || $status === '') continue;

                            $msg = WhatsAppMensagem::where('empresa_id', $empresaId)
                                ->where('provider_message_id', $id)
                                ->orderByDesc('id')
                                ->first();

                            if ($msg) {
                                $msg->status = $status;
                                $msg->payload = $payload;
                                $msg->updated_at = Carbon::now();
                                $msg->save();
                            }
                        }
                    }
                }
            }
        }

        return response()->json(['ok' => true], Response::HTTP_OK);
    }

    private function validateSignature(Request $request, string $appSecret): bool
    {
        // Se não houver app secret configurado, aceita (modo dev).
        if ($appSecret === '') {
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $received = substr($header, 7);
        $raw = $request->getContent();
        $calc = hash_hmac('sha256', $raw, $appSecret);

        return hash_equals($calc, $received);
    }

    private function resolveClienteId(int $empresaId, string $numero): ?int
    {
        $n = $this->normalizeNumber($numero);
        if ($n === '') return null;

        // tenta por telefone (heurística simples)
        $c = Cliente::where('empresa_id', $empresaId)
            ->where('telefone', 'like', '%' . substr($n, -8))
            ->orderByDesc('id')
            ->first();

        return $c ? (int) $c->id : null;
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
