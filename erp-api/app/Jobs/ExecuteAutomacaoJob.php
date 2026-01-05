<?php

namespace App\Jobs;

use App\Models\AutomacaoConfig;
use App\Models\AutomacaoExecucao;
use App\Models\FinanceiroLancamento;
use App\Models\OrdemServico;
use App\Models\Pagamento;
use App\Services\WhatsAppSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAutomacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $execucaoId)
    {
        $this->onQueue('default');
    }

    public function handle(WhatsAppSenderService $sender): void
    {
        $exec = AutomacaoExecucao::find($this->execucaoId);
        if (! $exec) {
            return;
        }

        $exec->started_at = now();
        $exec->status = 'running';
        $exec->save();

        try {
            $cfg = $exec->automacao_config_id ? AutomacaoConfig::find((int) $exec->automacao_config_id) : null;
            if (! $cfg || ! (bool) $cfg->ativo) {
                $exec->status = 'skipped';
                $exec->mensagem = 'Automação desativada ou inexistente.';
                $exec->finished_at = now();
                $exec->save();
                return;
            }

            $payload = is_array($exec->payload) ? $exec->payload : [];

            $result = match ((string) $exec->acao) {
                'whatsapp_os_criada' => $this->acaoWhatsAppOsCriada($sender, $exec),
                'whatsapp_os_em_producao' => $this->acaoWhatsAppOsStatus($sender, $exec, 'em_producao'),
                'whatsapp_os_finalizada' => $this->acaoWhatsAppOsStatus($sender, $exec, 'finalizada'),
                'whatsapp_os_aguardando_pagamento_pix' => $this->acaoWhatsAppOsAguardandoPagamentoPix($sender, $exec),
                'whatsapp_financeiro_gerado' => $this->acaoWhatsAppFinanceiroGerado($sender, $exec),
                'whatsapp_pagamento_confirmado' => $this->acaoWhatsAppPagamentoConfirmado($sender, $exec),
                'whatsapp_lembrete_pagamento' => $this->acaoWhatsAppLembretePagamento($sender, $exec),
                'whatsapp_notificar_os_parada' => $this->acaoWhatsAppOsParada($sender, $exec),
                default => ['status' => 'skipped', 'mensagem' => 'Ação não suportada.'],
            };

            $exec->status = (string) ($result['status'] ?? 'skipped');
            $exec->mensagem = (string) ($result['mensagem'] ?? null);
            $exec->finished_at = now();
            $exec->save();
        } catch (\Throwable $e) {
            $exec->status = 'error';
            $exec->mensagem = $e->getMessage();
            $exec->finished_at = now();
            $exec->save();
            // não rethrow (evita duplicar por retry)
        }
    }

    private function acaoWhatsAppOsStatus(WhatsAppSenderService $sender, AutomacaoExecucao $exec, string $statusEsperado): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $statusNovo = (string) ($payload['status_novo'] ?? '');

        if ($statusNovo !== $statusEsperado) {
            return ['status' => 'skipped', 'mensagem' => 'Status não corresponde.'];
        }

        $osId = (int) ($payload['ordem_servico_id'] ?? $exec->entidade_id ?? 0);
        if ($osId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'OS não informada.'];
        }

        $os = OrdemServico::with(['cliente'])->where('empresa_id', $exec->empresa_id)->where('id', $osId)->first();
        if (! $os || ! $os->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Cliente/OS não encontrados.'];
        }

        $telefone = (string) ($os->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        if ($statusEsperado === 'em_producao') {
            $msg = "Olá {$os->cliente->nome}, sua OS {$os->numero} entrou em produção.";
        } else {
            $msg = "Olá {$os->cliente->nome}, sua OS {$os->numero} foi finalizada. Obrigado!";
        }

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $os->cliente_id ? (int) $os->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            $os->id ? (int) $os->id : null,
            $os->id ? (int) $os->id : null,
            'text'
        );

        return ['status' => 'success', 'mensagem' => 'WhatsApp enviado.'];
    }

    private function acaoWhatsAppOsCriada(WhatsAppSenderService $sender, AutomacaoExecucao $exec): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $osId = (int) ($payload['ordem_servico_id'] ?? $exec->entidade_id ?? 0);
        if ($osId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'OS não informada.'];
        }

        $os = OrdemServico::with(['cliente'])->where('empresa_id', $exec->empresa_id)->where('id', $osId)->first();
        if (! $os || ! $os->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Cliente/OS não encontrados.'];
        }

        $telefone = (string) ($os->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        $msg = "Olá {$os->cliente->nome}, sua OS {$os->numero} foi criada e já está em andamento."
            . " Vamos te avisando por aqui.";

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $os->cliente_id ? (int) $os->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            $os->id ? (int) $os->id : null,
            $os->id ? (int) $os->id : null,
            'text'
        );

        return ['status' => 'success', 'mensagem' => 'WhatsApp enviado.'];
    }

    private function acaoWhatsAppFinanceiroGerado(WhatsAppSenderService $sender, AutomacaoExecucao $exec): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $lancId = (int) ($payload['financeiro_lancamento_id'] ?? $exec->entidade_id ?? 0);
        if ($lancId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento não informado.'];
        }

        $lanc = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $exec->empresa_id)
            ->where('id', $lancId)
            ->first();

        if (! $lanc || ! $lanc->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento/cliente não encontrados.'];
        }

        $telefone = (string) ($lanc->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        $valor = number_format((float) $lanc->valor, 2, ',', '.');
        $osNumero = $lanc->ordemServico ? (string) ($lanc->ordemServico->numero ?? '') : '';
        $venc = $lanc->data_vencimento;

        $msg = "Olá {$lanc->cliente->nome}, foi gerado um pagamento";
        if ($osNumero !== '') {
            $msg .= " da OS {$osNumero}";
        }
        $msg .= ". Valor R$ {$valor}.";
        if ($venc) {
            $msg .= " Vencimento: {$venc}.";
        }

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $lanc->cliente_id ? (int) $lanc->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            (int) $lanc->id,
            $lanc->ordem_servico_id ? (int) $lanc->ordem_servico_id : null,
            'text'
        );

        return ['status' => 'success', 'mensagem' => 'WhatsApp enviado.'];
    }

    private function acaoWhatsAppPagamentoConfirmado(WhatsAppSenderService $sender, AutomacaoExecucao $exec): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $lancId = (int) ($payload['financeiro_lancamento_id'] ?? $exec->entidade_id ?? 0);
        if ($lancId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento não informado.'];
        }

        $lanc = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $exec->empresa_id)
            ->where('id', $lancId)
            ->first();

        if (! $lanc || ! $lanc->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento/cliente não encontrados.'];
        }

        if ((string) $lanc->status !== 'pago') {
            return ['status' => 'skipped', 'mensagem' => 'Pagamento ainda não está confirmado.'];
        }

        $telefone = (string) ($lanc->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        $osNumero = $lanc->ordemServico ? (string) ($lanc->ordemServico->numero ?? '') : '';
        $msg = "Olá {$lanc->cliente->nome}, recebemos seu pagamento";
        if ($osNumero !== '') {
            $msg .= " da OS {$osNumero}";
        }
        $msg .= ". Obrigado!";

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $lanc->cliente_id ? (int) $lanc->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            (int) $lanc->id,
            $lanc->ordem_servico_id ? (int) $lanc->ordem_servico_id : null,
            'text'
        );

        return ['status' => 'success', 'mensagem' => 'Confirmação enviada.'];
    }

    private function acaoWhatsAppOsAguardandoPagamentoPix(WhatsAppSenderService $sender, AutomacaoExecucao $exec): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $statusNovo = (string) ($payload['status_novo'] ?? '');
        if ($statusNovo !== 'aguardando_pagamento') {
            return ['status' => 'skipped', 'mensagem' => 'Status não corresponde.'];
        }

        $osId = (int) ($payload['ordem_servico_id'] ?? $exec->entidade_id ?? 0);
        if ($osId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'OS não informada.'];
        }

        $os = OrdemServico::with(['cliente'])->where('empresa_id', $exec->empresa_id)->where('id', $osId)->first();
        if (! $os || ! $os->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Cliente/OS não encontrados.'];
        }

        $telefone = (string) ($os->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        $lanc = FinanceiroLancamento::where('empresa_id', $exec->empresa_id)
            ->where('ordem_servico_id', $os->id)
            ->where('status', '!=', 'cancelado')
            ->orderByDesc('id')
            ->first();

        if (! $lanc) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento financeiro não encontrado.'];
        }

        $pag = Pagamento::where('empresa_id', $exec->empresa_id)
            ->where('financeiro_lancamento_id', $lanc->id)
            ->where('metodo', 'pix')
            ->orderByDesc('id')
            ->first();

        if (! $pag || empty($pag->qr_code_text)) {
            return ['status' => 'skipped', 'mensagem' => 'PIX ainda não foi gerado.'];
        }

        $valor = number_format((float) $lanc->valor, 2, ',', '.');
        $msg = "PIX gerado para OS {$os->numero} no valor de R$ {$valor}.\n\n";
        $msg .= "Copia e cola:\n{$pag->qr_code_text}";

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $os->cliente_id ? (int) $os->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            (int) $lanc->id,
            $os->id ? (int) $os->id : null,
            'pix_qr'
        );

        return ['status' => 'success', 'mensagem' => 'PIX enviado por WhatsApp.'];
    }

    private function acaoWhatsAppLembretePagamento(WhatsAppSenderService $sender, AutomacaoExecucao $exec): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $lancId = (int) ($payload['financeiro_lancamento_id'] ?? $exec->entidade_id ?? 0);
        if ($lancId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento não informado.'];
        }

        $lanc = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $exec->empresa_id)
            ->where('id', $lancId)
            ->first();

        if (! $lanc || ! $lanc->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento/cliente não encontrados.'];
        }

        if ((string) $lanc->status !== 'pendente') {
            return ['status' => 'skipped', 'mensagem' => 'Lançamento não está pendente.'];
        }

        $telefone = (string) ($lanc->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        $valor = number_format((float) $lanc->valor, 2, ',', '.');
        $venc = $lanc->data_vencimento;
        $osNumero = $lanc->ordemServico ? (string) ($lanc->ordemServico->numero ?? '') : '';

        $msg = "Olá {$lanc->cliente->nome}, lembrete de pagamento";
        if ($osNumero !== '') {
            $msg .= " da OS {$osNumero}";
        }
        $msg .= ". Valor R$ {$valor}.";
        if ($venc) {
            $msg .= " Vencimento: {$venc}.";
        }

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $lanc->cliente_id ? (int) $lanc->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            (int) $lanc->id,
            $lanc->ordem_servico_id ? (int) $lanc->ordem_servico_id : null,
            'text'
        );

        return ['status' => 'success', 'mensagem' => 'Lembrete enviado.'];
    }

    private function acaoWhatsAppOsParada(WhatsAppSenderService $sender, AutomacaoExecucao $exec): array
    {
        $payload = is_array($exec->payload) ? $exec->payload : [];
        $osId = (int) ($payload['ordem_servico_id'] ?? $exec->entidade_id ?? 0);
        if ($osId <= 0) {
            return ['status' => 'skipped', 'mensagem' => 'OS não informada.'];
        }

        $os = OrdemServico::with(['cliente'])
            ->where('empresa_id', $exec->empresa_id)
            ->where('id', $osId)
            ->first();

        if (! $os || ! $os->cliente) {
            return ['status' => 'skipped', 'mensagem' => 'Cliente/OS não encontrados.'];
        }

        $telefone = (string) ($os->cliente->telefone ?? '');
        if ($telefone === '') {
            return ['status' => 'skipped', 'mensagem' => 'Cliente sem telefone.'];
        }

        $dias = (int) ($payload['dias'] ?? 0);
        $status = (string) ($payload['status_atual'] ?? $os->status_atual);

        $msg = "Olá {$os->cliente->nome}, sua OS {$os->numero} está em '{$status}' há {$dias} dias. Se precisar de ajuda, responda esta mensagem.";

        $sender->sendTextMessage(
            (int) $exec->empresa_id,
            $os->cliente_id ? (int) $os->cliente_id : null,
            $telefone,
            $msg,
            'automacao',
            $os->id ? (int) $os->id : null,
            $os->id ? (int) $os->id : null,
            'text'
        );

        return ['status' => 'success', 'mensagem' => 'Notificação enviada.'];
    }
}
