<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\ProdutoCompraItem;
use App\Models\CompraItem;
use Illuminate\Support\Facades\DB;

class ProdutoVivoCalculoService
{
    /**
     * Recalcula e persiste os campos:
     * - custo_total
     * - lucro
     * - margem_percentual
     *
    * Regras (Produto Vivo):
    * - custo_total = custo_base + soma(item_compra.preco_medio * quantidade)
    *   (+ componentes, se existirem)
     * - lucro = preco_venda - custo_total
     * - margem_percentual = (lucro / custo_total) * 100
     *
     * Bloqueios:
     * - custo_total <= 0
     * - preco_venda < custo_total
     * - lucro < 0
     */
    public function recalcular(Produto $produto): void
    {
        $custoPorBase = $this->custoTotalPorBase($produto);
        $precoBase = $this->precoBase($produto);

        // Modelo final: Produto apenas planeja.
        // Custo aqui é estimativa (com base em preço médio de compras) e não deve bloquear cadastro/edição.
        $lucro = $precoBase - $custoPorBase;
        $margem = $custoPorBase > 0 ? ($lucro / $custoPorBase) * 100.0 : 0.0;

        $produto->custo_total = round($custoPorBase, 4);
        $produto->lucro = round($lucro, 4);
        $produto->margem_percentual = round($margem, 4);

        // compatibilidade: alinhar colunas antigas
        if (property_exists($produto, 'custo_calculado')) {
            $produto->custo_calculado = $produto->custo_total;
        }
        if (property_exists($produto, 'preco_venda')) {
            $produto->preco_venda = $precoBase;
        }
        if (property_exists($produto, 'preco') && ($produto->preco === null || (float) $produto->preco === 0.0)) {
            $produto->preco = $precoBase;
        }
        if (property_exists($produto, 'preco_final')) {
            $produto->preco_final = $precoBase;
        }

        $produto->save();
    }

    /**
     * Calcula valores para um fator (ex.: OS) sem duplicar lógica.
     *
     * @return array{fator:float,custo_total:float,preco_total:float,lucro:float,margem_percentual:float,valor_unitario:float}
     */
    public function calcularParaFator(Produto $produto, float $fator): array
    {
        if ($fator <= 0) {
            throw new \RuntimeException('Medida inválida: o fator de cálculo deve ser maior que zero.', 422);
        }

        $custoPorBase = $this->custoTotalPorBase($produto);
        if ($custoPorBase <= 0) {
            throw new \RuntimeException('Custo total deve ser maior que zero.', 422);
        }

        $precoBase = $this->precoBase($produto);
        $custoTotal = $custoPorBase * $fator;
        $precoTotal = $precoBase * $fator;

        if ($precoTotal < $custoTotal) {
            throw new \RuntimeException('Preço total não pode ser menor que o custo total.', 422);
        }

        $lucro = $precoTotal - $custoTotal;
        if ($lucro < 0) {
            throw new \RuntimeException('Lucro não pode ser negativo.', 422);
        }

        $margem = ($lucro / $custoTotal) * 100.0;

        return [
            'fator' => $fator,
            'valor_unitario' => $precoBase,
            'custo_total' => round($custoTotal, 4),
            'preco_total' => round($precoTotal, 4),
            'lucro' => round($lucro, 4),
            'margem_percentual' => round($margem, 4),
        ];
    }

    private function precoBase(Produto $produto): float
    {
        $pb = (float) ($produto->preco_base ?? 0);
        if ($pb > 0) {
            return $pb;
        }

        // compat
        $pv = (float) ($produto->preco_venda ?? 0);
        if ($pv > 0) {
            return $pv;
        }

        return (float) ($produto->preco ?? $produto->preco_final ?? $produto->preco_manual ?? 0);
    }

    private function custoTotalPorBase(Produto $produto): float
    {
        $custoBase = (float) ($produto->custo_base ?? 0);

        // Compras: custo real por preço médio ponderado (preco_medio)
        $comprasItens = ProdutoCompraItem::query()
            ->where('empresa_id', $produto->empresa_id)
            ->where('produto_id', $produto->id)
            ->with(['compraItem'])
            ->get();

        $custoCompras = 0.0;
        foreach ($comprasItens as $pci) {
            $qBase = (float) ($pci->quantidade_base ?? 0);
            if ($qBase <= 0) {
                continue;
            }

            $custoUnit = (float) ($pci->compraItem?->preco_medio ?? 0);
            $custoCompras += $custoUnit * $qBase;
        }

        // Componentes: manter custo real já modelado no sistema (assumido por base)
        $custoComponentes = 0.0;
        if (method_exists($produto, 'produtoComponentes')) {
            $custoComponentes = (float) $produto->produtoComponentes()
                ->where('status', 'ativo')
                ->sum('custo_total');
        }

        return $custoBase + $custoCompras + $custoComponentes;
    }

    /**
     * @param array<int,array{compra_item_id:int,quantidade_base:float}> $itens
     */
    public function validarComprasItens(int $empresaId, array $itens): void
    {
        foreach ($itens as $i) {
            $itemId = (int) ($i['compra_item_id'] ?? 0);
            $q = (float) ($i['quantidade_base'] ?? 0);

            if (! $itemId) {
                throw new \RuntimeException('Item de compra inválido.', 422);
            }
            if ($q <= 0) {
                throw new \RuntimeException('Quantidade do item de compra deve ser maior que zero.', 422);
            }

            $ok = CompraItem::query()
                ->where('empresa_id', $empresaId)
                ->where('id', $itemId)
                ->where('ativo', true)
                ->exists();

            if (! $ok) {
                throw new \RuntimeException('Item de compra não encontrado.', 404);
            }
        }
    }

    // custos legados (insumos/processos/acabamentos/equipamentos/mão de obra) removidos:
    // custo real agora é centralizado em Compras.

    /**
     * Valida materiais antes de persistir.
     *
     * @param int $empresaId
     * @param int $produtoId
     * @param array<int,array{material_id:int,quantidade:float}> $materiais
     */
    public function validarMateriais(int $empresaId, int $produtoId, array $materiais): void
    {
        foreach ($materiais as $m) {
            $materialId = (int) ($m['material_id'] ?? 0);
            $q = (float) ($m['quantidade_base'] ?? $m['quantidade'] ?? 0);

            if (! $materialId) {
                throw new \RuntimeException('Material inválido.', 422);
            }
            if ($materialId === $produtoId) {
                throw new \RuntimeException('Produto não pode usar ele mesmo como material.', 422);
            }
            if ($q <= 0) {
                throw new \RuntimeException('Quantidade do material deve ser maior que zero.', 422);
            }

            $ok = Produto::query()
                ->where('empresa_id', $empresaId)
                ->where('id', $materialId)
                ->where(function ($q) {
                    $q->where('ativo', true)->orWhere('status', 'ativo');
                })
                ->exists();

            if (! $ok) {
                throw new \RuntimeException('Material não encontrado.', 404);
            }

            // bloqueio de ciclo (direto/indireto)
            if ($this->existeCiclo($empresaId, $produtoId, $materialId)) {
                throw new \RuntimeException('Composição inválida: ciclo detectado nos materiais.', 422);
            }
        }
    }

    private function existeCiclo(int $empresaId, int $produtoId, int $materialId): bool
    {
        // DFS: materialId não pode (direta/indiretamente) depender de produtoId
        $visitados = [];
        $pilha = [$materialId];

        while (! empty($pilha)) {
            $atual = array_pop($pilha);
            if (isset($visitados[$atual])) {
                continue;
            }
            $visitados[$atual] = true;

            if ($atual === $produtoId) {
                return true;
            }

            $ids = DB::table('produto_materiais')
                ->where('empresa_id', $empresaId)
                ->where('produto_id', $atual)
                ->pluck(DB::raw('COALESCE(material_id, material_produto_id)'))
                ->map(fn ($v) => (int) $v)
                ->all();

            foreach ($ids as $next) {
                if ($next && ! isset($visitados[$next])) {
                    $pilha[] = $next;
                }
            }
        }

        return false;
    }
}
