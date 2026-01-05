<?php

namespace Tests\Feature\Api\V1;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FinanceiroLancamento;
use App\Models\OrdemServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoWebhookConciliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function assinaturaHeaders(string $secret, string $paymentId, string $requestId, int $ts): array
    {
        $manifest = 'id:' . strtolower($paymentId) . ';request-id:' . $requestId . ';ts:' . $ts . ';';
        $hash = hash_hmac('sha256', $manifest, $secret);

        return [
            'x-request-id' => $requestId,
            'x-signature' => 'ts=' . $ts . ',v1=' . $hash,
        ];
    }

    public function test_webhook_aprova_pagamento_quando_valor_bate(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa MP', 'status' => true]);
        $empresa->mercado_pago_webhook_secret = 'secret123';
        $empresa->mercado_pago_access_token = 'token123';
        $empresa->save();

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente MP',
            'telefone' => '5511999999999',
            'email' => 'cliente@mp.test',
            'status' => 'ativo',
        ]);

        $os = OrdemServico::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-MP-1',
            'descricao' => 'OS MP',
            'data_entrega' => now()->addDays(2)->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 150.50,
        ]);

        $lancamento = FinanceiroLancamento::create([
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => $os->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'descricao' => 'Teste',
            'valor' => 150.50,
            'status' => 'pendente',
            'data_vencimento' => now()->addDays(1)->toDateString(),
            'data_pagamento' => null,
        ]);

        $paymentId = '9990001';
        $mpPayload = [
            'id' => (int) $paymentId,
            'status' => 'approved',
            'transaction_amount' => 150.50,
            'date_approved' => now()->toIso8601String(),
            'metadata' => [
                'empresa_id' => $empresa->id,
                'financeiro_lancamento_id' => $lancamento->id,
                'ordem_servico_id' => $os->id,
            ],
        ];

        Http::fake([
            'https://api.mercadopago.com/v1/payments/*' => Http::response($mpPayload, 200),
        ]);

        $requestId = 'req-1';
        $ts = now()->timestamp;
        $headers = $this->assinaturaHeaders('secret123', $paymentId, $requestId, $ts);

        $res = $this->withHeaders($headers)->postJson(
            'api/v1/mercado-pago/webhook?empresa_id=' . $empresa->id . '&data[id]=' . $paymentId,
            ['data' => ['id' => $paymentId]]
        );

        $res->assertStatus(200);

        $lancamento->refresh();
        $os->refresh();

        $this->assertSame('pago', $lancamento->status);
        $this->assertNotNull($lancamento->data_pagamento);
        $this->assertSame('finalizada', $os->status_atual);
    }

    public function test_webhook_nao_aprova_quando_valor_diverge(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa MP', 'status' => true]);
        $empresa->mercado_pago_webhook_secret = 'secret123';
        $empresa->mercado_pago_access_token = 'token123';
        $empresa->save();

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente MP',
            'telefone' => '5511999999999',
            'email' => 'cliente@mp.test',
            'status' => 'ativo',
        ]);

        $os = OrdemServico::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-MP-2',
            'descricao' => 'OS MP',
            'data_entrega' => now()->addDays(2)->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 200.00,
        ]);

        $lancamento = FinanceiroLancamento::create([
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => $os->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'descricao' => 'Teste',
            'valor' => 200.00,
            'status' => 'pendente',
            'data_vencimento' => now()->addDays(1)->toDateString(),
            'data_pagamento' => null,
        ]);

        $paymentId = '9990002';
        $mpPayload = [
            'id' => (int) $paymentId,
            'status' => 'approved',
            'transaction_amount' => 199.99,
            'metadata' => [
                'empresa_id' => $empresa->id,
                'financeiro_lancamento_id' => $lancamento->id,
                'ordem_servico_id' => $os->id,
            ],
        ];

        Http::fake([
            'https://api.mercadopago.com/v1/payments/*' => Http::response($mpPayload, 200),
        ]);

        $requestId = 'req-2';
        $ts = now()->timestamp;
        $headers = $this->assinaturaHeaders('secret123', $paymentId, $requestId, $ts);

        $res = $this->withHeaders($headers)->postJson(
            'api/v1/mercado-pago/webhook?empresa_id=' . $empresa->id . '&data[id]=' . $paymentId,
            ['data' => ['id' => $paymentId]]
        );

        $res->assertStatus(422);
        $res->assertJsonFragment(['message' => 'Valor do pagamento divergente']);

        $lancamento->refresh();
        $os->refresh();

        $this->assertSame('pendente', $lancamento->status);
        $this->assertNotSame('finalizada', $os->status_atual);
    }
}
