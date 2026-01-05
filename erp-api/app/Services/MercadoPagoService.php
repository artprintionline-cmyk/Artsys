<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class MercadoPagoService
{
    private const BASE_URL = 'https://api.mercadopago.com';

    private function token(Empresa $empresa): string
    {
        $token = (string) ($empresa->mercado_pago_access_token ?? '');
        if ($token === '') {
            throw new \RuntimeException('Mercado Pago não configurado para esta empresa.');
        }
        return $token;
    }

    /**
     * Cria pagamento PIX no Mercado Pago e retorna QR Code + ids.
     *
     * @return array{payment_id:string,status:string,qr_code_base64:?string,qr_code_text:?string,raw:array}
     */
    public function criarPagamentoPix(
        Empresa $empresa,
        float $valor,
        string $descricao,
        string $payerEmail,
        string $notificationUrl,
        array $metadata = []
    ): array {
        $body = [
            'transaction_amount' => round($valor, 2),
            'description' => $descricao,
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $payerEmail,
            ],
            'notification_url' => $notificationUrl,
            'metadata' => $metadata,
        ];

        $res = Http::withToken($this->token($empresa))
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL . '/v1/payments', $body);

        $this->throwIfError($res);

        $data = (array) $res->json();

        $paymentId = (string) ($data['id'] ?? '');
        $status = (string) ($data['status'] ?? '');

        $tx = $data['point_of_interaction']['transaction_data'] ?? null;
        $qrCodeBase64 = is_array($tx) ? ($tx['qr_code_base64'] ?? null) : null;
        $qrCodeText = is_array($tx) ? ($tx['qr_code'] ?? null) : null;

        return [
            'payment_id' => $paymentId,
            'status' => $status,
            'qr_code_base64' => $qrCodeBase64,
            'qr_code_text' => $qrCodeText,
            'raw' => $data,
        ];
    }

    /**
     * Consulta um pagamento no Mercado Pago.
     */
    public function consultarPagamento(Empresa $empresa, string $paymentId): array
    {
        $res = Http::withToken($this->token($empresa))
            ->acceptJson()
            ->get(self::BASE_URL . '/v1/payments/' . urlencode($paymentId));

        $this->throwIfError($res);
        return (array) $res->json();
    }

    private function throwIfError(Response $res): void
    {
        if ($res->successful()) {
            return;
        }

        $msg = 'Erro Mercado Pago';
        $json = $res->json();
        if (is_array($json) && isset($json['message'])) {
            $msg = (string) $json['message'];
        }

        throw new \RuntimeException($msg . ' (HTTP ' . $res->status() . ')');
    }
}
