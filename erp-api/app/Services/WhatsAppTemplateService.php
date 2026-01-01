<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;

class WhatsAppTemplateService
{
    /**
     * Renderiza um template substituindo variáveis básicas.
     *
     * @param int $empresaId
     * @param string $chave
     * @param array $vars ['cliente' => string, 'valor' => string, 'os' => string]
     * @return string
     */
    public function renderTemplate(int $empresaId, string $chave, array $vars = []): string
    {
        $template = WhatsAppTemplate::where('empresa_id', $empresaId)
            ->where('chave', $chave)
            ->where('ativo', true)
            ->first();

        if (! $template) {
            return '';
        }

        $content = $template->conteudo;

        $mapping = [
            '{{cliente}}' => $vars['cliente'] ?? '',
            '{{valor}}' => $vars['valor'] ?? '',
            '{{os}}' => $vars['os'] ?? '',
        ];

        return strtr($content, $mapping);
    }
}
