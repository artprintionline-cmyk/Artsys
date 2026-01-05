<?php

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Plano;
use Illuminate\Support\Facades\Cache;

class SaasAssinaturaService
{
    public function getAssinaturaAtual(int $empresaId): ?Assinatura
    {
        return Assinatura::with('plano')->where('empresa_id', $empresaId)->first();
    }

    /**
     * @return array{read_only:bool,motivo:?string,status:?string,trial_expired:bool,expires_at:?string,plano:?array,limites:array}
     */
    public function statusAcesso(int $empresaId): array
    {
        $assinatura = $this->getAssinaturaAtual($empresaId);

        // Backward-compat: se ainda não existe assinatura (ambiente legado), libera.
        if (! $assinatura) {
            return [
                'read_only' => false,
                'motivo' => null,
                'status' => null,
                'trial_expired' => false,
                'expires_at' => null,
                'plano' => null,
                'limites' => [],
            ];
        }

        $status = (string) $assinatura->status;
        $fim = $assinatura->fim;

        $trialExpired = $status === 'trial' && $fim && $fim->isPast();
        $readOnly = false;
        $motivo = null;

        if ($status === 'suspensa') {
            $readOnly = true;
            $motivo = 'Assinatura suspensa: acesso somente leitura.';
        } elseif ($status === 'cancelada') {
            $readOnly = true;
            $motivo = 'Assinatura cancelada: acesso somente leitura.';
        } elseif ($trialExpired) {
            $readOnly = true;
            $motivo = 'Trial expirado: acesso somente leitura.';
        }

        $plano = $assinatura->plano;
        $limites = is_array($plano?->limites) ? $plano->limites : [];

        return [
            'read_only' => $readOnly,
            'motivo' => $motivo,
            'status' => $status,
            'trial_expired' => $trialExpired,
            'expires_at' => $fim ? $fim->toIso8601String() : null,
            'plano' => $plano ? [
                'id' => (int) $plano->id,
                'nome' => (string) $plano->nome,
                'preco' => (float) $plano->preco,
                'ativo' => (bool) $plano->ativo,
            ] : null,
            'limites' => $limites,
        ];
    }

    public function planoPermite(int $empresaId, string $featureKey): bool
    {
        $assinatura = $this->getAssinaturaAtual($empresaId);
        if (! $assinatura || ! $assinatura->plano) {
            return true;
        }

        $limites = is_array($assinatura->plano->limites) ? $assinatura->plano->limites : [];
        $val = $limites[$featureKey] ?? null;

        // default: permitido se não configurado
        if ($val === null) {
            return true;
        }

        return (bool) $val;
    }

    public function limiteInt(int $empresaId, string $key, ?int $default = null): ?int
    {
        $assinatura = $this->getAssinaturaAtual($empresaId);
        if (! $assinatura || ! $assinatura->plano) {
            return $default;
        }

        $limites = is_array($assinatura->plano->limites) ? $assinatura->plano->limites : [];
        if (! array_key_exists($key, $limites)) {
            return $default;
        }

        $v = $limites[$key];
        if ($v === null) {
            return $default;
        }

        return (int) $v;
    }
}
