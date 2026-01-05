<?php

namespace App\Services;

use App\Models\WhatsAppConfiguracao;
use App\Models\WhatsAppMensagem;
use App\Models\WhatsAppTemplate;
use Carbon\Carbon;

class WhatsAppSenderService
{
    /**
     * Compatibilidade: método antigo usado por testes/fluxos prévios.
     */
    public function sendMessage(int $empresaId, ?int $clienteId, string $numero, string $mensagem, string $contexto, ?int $referenciaId = null): bool
    {
        return $this->sendTextMessage($empresaId, $clienteId, $numero, $mensagem, $contexto, $referenciaId);
    }

    public function sendTextMessage(
        int $empresaId,
        ?int $clienteId,
        string $numero,
        string $mensagem,
        string $contexto,
        ?int $referenciaId = null,
        ?int $ordemServicoId = null,
        string $tipo = 'text'
    ): bool
    {
        $numeroNormalizado = $this->normalizeNumber($numero);

        $config = WhatsAppConfiguracao::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderByDesc('id')
            ->first();

        // fallback seguro: registra como "enviado" (simulado) quando não houver config.
        if (! $config || strtolower((string) $config->provedor) !== 'cloud_api') {
            WhatsAppMensagem::create([
                'empresa_id' => $empresaId,
                'ordem_servico_id' => $ordemServicoId,
                'cliente_id' => $clienteId,
                'numero' => $numeroNormalizado,
                'mensagem' => $mensagem,
                'direcao' => 'saida',
                'tipo' => $tipo,
                'provider_message_id' => null,
                'status' => 'enviado',
                'contexto' => $contexto,
                'referencia_id' => $referenciaId,
                'payload' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return true;
        }

        if (empty($config->token) || empty($config->phone_number_id)) {
            throw new \RuntimeException('WhatsApp Cloud API não configurado (token/phone_number_id).');
        }

        $client = app(WhatsAppCloudApiClient::class);
        $apiVersion = (string) ($config->api_version ?: 'v19.0');

        $sent = $client->sendText(
            $apiVersion,
            (string) $config->phone_number_id,
            (string) $config->token,
            $numeroNormalizado,
            $mensagem
        );

        WhatsAppMensagem::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordemServicoId,
            'cliente_id' => $clienteId,
            'numero' => $numeroNormalizado,
            'mensagem' => $mensagem,
            'direcao' => 'saida',
            'tipo' => $tipo,
            'provider_message_id' => $sent['provider_message_id'] ?? null,
            'status' => 'enviado',
            'contexto' => $contexto,
            'referencia_id' => $referenciaId,
            'payload' => $sent['raw'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return true;
    }

    public function sendTemplateMessage(int $empresaId, ?int $clienteId, string $numero, string $templateKey, string $contexto, ?int $referenciaId = null): bool
    {
        $numeroNormalizado = $this->normalizeNumber($numero);

        $config = WhatsAppConfiguracao::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderByDesc('id')
            ->first();

        /** @var WhatsAppTemplate|null $tpl */
        $tpl = WhatsAppTemplate::where('empresa_id', $empresaId)
            ->where('chave', $templateKey)
            ->where('ativo', true)
            ->first();

        if (! $tpl) {
            throw new \RuntimeException('Template WhatsApp não encontrado.');
        }

        // fallback para texto quando não tiver config cloud.
        if (! $config || strtolower((string) $config->provedor) !== 'cloud_api') {
            return $this->sendTextMessage($empresaId, $clienteId, $numeroNormalizado, (string) $tpl->conteudo, $contexto, $referenciaId, null, 'template');
        }

        if (empty($config->token) || empty($config->phone_number_id)) {
            throw new \RuntimeException('WhatsApp Cloud API não configurado (token/phone_number_id).');
        }

        if ((string) $tpl->tipo !== 'template' || empty($tpl->template_nome)) {
            // template do provider não configurado: cai no texto.
            return $this->sendTextMessage($empresaId, $clienteId, $numeroNormalizado, (string) $tpl->conteudo, $contexto, $referenciaId, null, 'template');
        }

        $client = app(WhatsAppCloudApiClient::class);
        $apiVersion = (string) ($config->api_version ?: 'v19.0');

        $sent = $client->sendTemplate(
            $apiVersion,
            (string) $config->phone_number_id,
            (string) $config->token,
            $numeroNormalizado,
            (string) $tpl->template_nome,
            (string) ($tpl->template_linguagem ?: 'pt_BR')
        );

        WhatsAppMensagem::create([
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'numero' => $numeroNormalizado,
            'mensagem' => (string) $tpl->conteudo,
            'direcao' => 'saida',
            'tipo' => 'template',
            'provider_message_id' => $sent['provider_message_id'] ?? null,
            'status' => 'enviado',
            'contexto' => $contexto,
            'referencia_id' => $referenciaId,
            'payload' => $sent['raw'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return true;
    }

    private function normalizeNumber(string $numero): string
    {
        $digits = preg_replace('/\D+/', '', $numero) ?? '';
        $digits = ltrim($digits, '0');

        // Heurística BR: se vier sem DDI, assume 55.
        if ($digits !== '' && ! str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            return '55' . $digits;
        }

        return $digits;
    }
}
