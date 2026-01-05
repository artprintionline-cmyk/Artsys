<?php

namespace App\Services;

use App\Models\EstoqueMovimentacao;
use App\Models\EstoqueProduto;
use App\Models\EstoqueInsumo;
use App\Models\EstoqueInsumoMovimentacao;
use App\Models\OrdemServico;
use App\Models\OsItem;
use App\Models\Produto;
use App\Models\ProdutoInsumo;
use Illuminate\Support\Facades\DB;

class EstoqueService
{
    public function ajustar(
        int $empresaId,
        int $produtoId,
        string $tipo,
        float $quantidade,
        string $origem = 'ajuste',
        ?int $origemId = null,
        ?string $motivo = null
    ): EstoqueProduto
    {
        $tipoNorm = strtolower(trim($tipo));
        if (! in_array($tipoNorm, ['entrada', 'saida'], true)) {
            throw new \InvalidArgumentException('Tipo inválido.');
        }
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade inválida.');
        }

        $origemNorm = strtolower(trim($origem));
        $motivoNorm = $motivo !== null ? trim($motivo) : null;

        if ($origemNorm === 'ajuste' && ($motivoNorm === null || $motivoNorm === '')) {
            throw new \InvalidArgumentException('Motivo é obrigatório para ajuste de estoque.');
        }

        return DB::transaction(function () use ($empresaId, $produtoId, $tipoNorm, $quantidade, $origemNorm, $origemId, $motivoNorm) {
            /** @var EstoqueProduto $estoque */
            $estoque = EstoqueProduto::firstOrCreate(
                ['empresa_id' => $empresaId, 'produto_id' => $produtoId],
                ['quantidade_atual' => 0, 'estoque_minimo' => 0]
            );

            $atual = (float) $estoque->quantidade_atual;
            $novo = $tipoNorm === 'entrada' ? $atual + $quantidade : $atual - $quantidade;

            if ($novo < 0) {
                throw new \RuntimeException('Estoque insuficiente.');
            }

            $estoque->quantidade_atual = $novo;
            $estoque->save();

            EstoqueMovimentacao::create([
                'empresa_id' => $empresaId,
                'produto_id' => $produtoId,
                'tipo' => $tipoNorm,
                'quantidade' => $quantidade,
                'origem' => $origemNorm,
                'origem_id' => $origemId,
                'motivo' => $motivoNorm,
            ]);

            return $estoque;
        });
    }

    public function consumirPorOrdemServico(OrdemServico $os): void
    {
        $empresaId = (int) $os->empresa_id;

        $ja = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->where('origem', 'os')
            ->where('origem_id', $os->id)
            ->exists();

        $jaInsumos = EstoqueInsumoMovimentacao::where('empresa_id', $empresaId)
            ->where('origem', 'os')
            ->where('origem_id', $os->id)
            ->exists();

        if ($ja && $jaInsumos) {
            return;
        }

        $os->loadMissing(['itens']);

        DB::transaction(function () use ($empresaId, $os, $ja, $jaInsumos) {
            $itens = $os->itens->where('status', 'ativo');

            // Consumo por materiais: quantidade_base * fator
            $porProduto = [];
            // Consumo por insumos: quantidade_base * fator
            $porInsumo = [];

            /** @var OsItem $it */
            foreach ($itens as $it) {
                $produto = Produto::with(['materiaisPivot.materialProduto', 'materiaisPivot.materialLegado'])
                    ->where('empresa_id', $empresaId)
                    ->where('id', (int) $it->produto_id)
                    ->first();

                if (! $produto) {
                    continue;
                }

                $fator = app(ProdutoFatorBaseService::class)->fromOsItem($produto, $it);
                if ($fator <= 0) {
                    throw new \RuntimeException('Medida inválida: o fator de cálculo deve ser maior que zero.');
                }

                foreach ($produto->materiaisPivot as $pm) {
                    $material = $pm->materialProduto ?: $pm->materialLegado;
                    if (! $material) {
                        continue;
                    }

                    // Só consome itens controlados em estoque.
                    if (property_exists($material, 'controla_estoque') && ! (bool) $material->controla_estoque) {
                        continue;
                    }
                    if (property_exists($material, 'ativo') && ! (bool) $material->ativo && (string) ($material->status ?? '') !== 'ativo') {
                        continue;
                    }

                    $qBase = (float) ($pm->quantidade_base ?? $pm->quantidade ?? 0);
                    if ($qBase <= 0) {
                        continue;
                    }

                    $consumo = $qBase * $fator;
                    $mid = (int) ($pm->material_id ?? $pm->material_produto_id);
                    if (! $mid) {
                        continue;
                    }

                    $porProduto[$mid] = ($porProduto[$mid] ?? 0.0) + $consumo;
                }

                $insumos = ProdutoInsumo::query()
                    ->where('empresa_id', $empresaId)
                    ->where('produto_id', (int) $produto->id)
                    ->with(['insumo'])
                    ->get();

                foreach ($insumos as $pi) {
                    $insumo = $pi->insumo;
                    if (! $insumo) {
                        continue;
                    }
                    if (! (bool) $insumo->ativo) {
                        continue;
                    }
                    if (! (bool) $insumo->controla_estoque) {
                        continue;
                    }

                    $qBase = (float) ($pi->quantidade_base ?? 0);
                    if ($qBase <= 0) {
                        continue;
                    }

                    $consumo = $qBase * $fator;
                    $iid = (int) ($pi->insumo_id ?? 0);
                    if (! $iid) {
                        continue;
                    }

                    $porInsumo[$iid] = ($porInsumo[$iid] ?? 0.0) + $consumo;
                }
            }

            // Idempotência: se já houve consumo anteriormente, não repetir para o mesmo tipo.
            if ($ja) {
                $porProduto = [];
            }

            if ($jaInsumos) {
                $porInsumo = [];
            }

            // primeira passada: valida se há estoque suficiente
            foreach ($porProduto as $produtoId => $qtd) {
                $estoque = EstoqueProduto::firstOrCreate(
                    ['empresa_id' => $empresaId, 'produto_id' => (int) $produtoId],
                    ['quantidade_atual' => 0, 'estoque_minimo' => 0]
                );

                if ((float) $estoque->quantidade_atual - (float) $qtd < 0) {
                    throw new \RuntimeException('Estoque insuficiente para finalizar a OS.');
                }
            }

            foreach ($porInsumo as $insumoId => $qtd) {
                $estoque = EstoqueInsumo::firstOrCreate(
                    ['empresa_id' => $empresaId, 'insumo_id' => (int) $insumoId],
                    ['quantidade_atual' => 0, 'estoque_minimo' => 0]
                );

                if ((float) $estoque->quantidade_atual - (float) $qtd < 0) {
                    throw new \RuntimeException('Estoque insuficiente para finalizar a OS.');
                }
            }

            // segunda passada: aplica as saídas
            foreach ($porProduto as $produtoId => $qtd) {
                $this->ajustar($empresaId, (int) $produtoId, 'saida', (float) $qtd, 'os', (int) $os->id, "Consumo OS {$os->numero}");
            }

            foreach ($porInsumo as $insumoId => $qtd) {
                $estoque = EstoqueInsumo::firstOrCreate(
                    ['empresa_id' => $empresaId, 'insumo_id' => (int) $insumoId],
                    ['quantidade_atual' => 0, 'estoque_minimo' => 0]
                );

                $novo = (float) $estoque->quantidade_atual - (float) $qtd;
                if ($novo < 0) {
                    throw new \RuntimeException('Estoque insuficiente para finalizar a OS.');
                }

                $estoque->quantidade_atual = $novo;
                $estoque->save();

                EstoqueInsumoMovimentacao::create([
                    'empresa_id' => $empresaId,
                    'insumo_id' => (int) $insumoId,
                    'tipo' => 'saida',
                    'quantidade' => (float) $qtd,
                    'origem' => 'os',
                    'origem_id' => (int) $os->id,
                    'motivo' => "Consumo OS {$os->numero}",
                ]);
            }
        });
    }
}
