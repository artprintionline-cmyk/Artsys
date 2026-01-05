<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\FinanceiroLancamento;
use App\Models\Pagamento;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReenviarPixPendentesCommand extends Command
{
    protected $signature = 'financeiro:pix-reenviar-pendentes
        {--dias=2 : Reenvia apenas lançamentos criados há pelo menos X dias}
        {--intervalo-horas=24 : Intervalo mínimo entre envios para o mesmo pagamento}
        {--limite=200 : Quantidade máxima de reenvios por execução}';

    protected $description = 'Reenvia (best-effort) o PIX pendente via WhatsApp, sem criar nova cobrança.';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        $intervaloHoras = (int) $this->option('intervalo-horas');
        $limite = (int) $this->option('limite');

        $dias = max(0, $dias);
        $intervaloHoras = max(0, $intervaloHoras);
        $limite = max(1, $limite);

        $cutoffLancamento = Carbon::now()->subDays($dias);
        $cutoffEnvio = Carbon::now()->subHours($intervaloHoras);

        $query = Pagamento::query()
            ->where('metodo', 'pix')
            ->where('status', 'pendente')
            ->whereNotNull('qr_code_text')
            ->where(function ($q) use ($cutoffEnvio) {
                $q->whereNull('ultimo_envio_whatsapp_at')
                    ->orWhere('ultimo_envio_whatsapp_at', '<=', $cutoffEnvio);
            })
            ->orderBy('id');

        $total = 0;

        foreach ($query->limit($limite)->cursor() as $pagamento) {
            /** @var Pagamento $pagamento */
            $lancamento = FinanceiroLancamento::with(['cliente', 'ordemServico'])
                ->where('empresa_id', $pagamento->empresa_id)
                ->where('id', $pagamento->financeiro_lancamento_id)
                ->first();

            if (! $lancamento) {
                continue;
            }

            if ($lancamento->status !== 'pendente') {
                continue;
            }

            if ($lancamento->created_at && $lancamento->created_at->gt($cutoffLancamento)) {
                continue;
            }

            $cliente = $lancamento->cliente;
            if (! $cliente || empty($cliente->telefone)) {
                continue;
            }

            $valor = number_format((float) $lancamento->valor, 2, ',', '.');
            $osNumero = $lancamento->ordemServico ? ($lancamento->ordemServico->numero ?? $lancamento->ordemServico->numero_os ?? '') : '';

            $mensagem = "Lembrete de pagamento PIX";
            if ($osNumero !== '') {
                $mensagem .= " para OS {$osNumero}";
            }
            $mensagem .= " no valor de R$ {$valor}.\n\n";
            $mensagem .= "Copia e cola:\n{$pagamento->qr_code_text}";

            dispatch(new SendWhatsAppMessageJob(
                (int) $pagamento->empresa_id,
                $lancamento->cliente_id ? (int) $lancamento->cliente_id : null,
                (string) $cliente->telefone,
                $mensagem,
                'pix_reenvio_automatico',
                (int) $lancamento->id
            ));

            // Marca envio (best-effort)
            $pagamento->ultimo_envio_whatsapp_at = now();
            $pagamento->envios_whatsapp_count = (int) ($pagamento->envios_whatsapp_count ?? 0) + 1;
            $pagamento->save();

            $total++;
        }

        $this->info("Reenvios agendados: {$total}");
        return self::SUCCESS;
    }
}
