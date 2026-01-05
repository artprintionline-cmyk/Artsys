<?php

namespace App\Services;

use App\Models\ProdutoComposto;

class ProdutoCompostoPrecoService
{
    public function precoEfetivo(ProdutoComposto $composto): float
    {
        if ($composto->preco_base !== null && (float) $composto->preco_base > 0) {
            return (float) $composto->preco_base;
        }

        $sum = 0.0;
        $produtoPreco = app(ProdutoPrecoService::class);

        $composto->loadMissing(['itens.produto']);

        foreach ($composto->itens as $item) {
            if (! $item->produto) {
                continue;
            }
            $sum += ((float) $item->quantidade) * $produtoPreco->precoAtual($item->produto);
        }

        return $sum;
    }
}
