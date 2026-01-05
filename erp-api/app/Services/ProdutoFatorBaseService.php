<?php

namespace App\Services;

use App\Models\OsItem;
use App\Models\Produto;

class ProdutoFatorBaseService
{
    /**
     * Calcula o fator_base a partir das medidas informadas para uma OS.
     * Também normaliza os campos para persistência em os_itens.
     *
     * @param array<string,mixed> $input
     * @return array{fator:float,quantidade:float,comprimento:?float,largura:?float,altura:?float}
     */
    public function fromInput(Produto $produto, array $input): array
    {
        $forma = method_exists($produto, 'formaCalculoEfetiva')
            ? $produto->formaCalculoEfetiva()
            : (string) ($produto->forma_calculo ?? $produto->tipo_medida ?? 'unitario');

        if ($forma === 'metro_linear') {
            $comprimento = isset($input['comprimento']) ? (float) $input['comprimento'] : null;
            if ($comprimento === null || $comprimento <= 0) {
                // compat: permitir quantidade como comprimento
                $comprimento = isset($input['quantidade']) ? (float) $input['quantidade'] : 0.0;
            }

            if ($comprimento <= 0) {
                throw new \RuntimeException('Comprimento é obrigatório e deve ser maior que zero.', 422);
            }

            return [
                'fator' => $comprimento,
                'quantidade' => $comprimento,
                'comprimento' => $comprimento,
                'largura' => null,
                'altura' => null,
            ];
        }

        if ($forma === 'metro_quadrado') {
            $largura = isset($input['largura']) ? (float) $input['largura'] : 0.0;
            $altura = isset($input['altura']) ? (float) $input['altura'] : 0.0;

            if ($largura <= 0 || $altura <= 0) {
                throw new \RuntimeException('Largura e altura são obrigatórias e devem ser maiores que zero.', 422);
            }

            $fator = $largura * $altura;
            if ($fator <= 0) {
                throw new \RuntimeException('Medida inválida: área deve ser maior que zero.', 422);
            }

            return [
                'fator' => $fator,
                'quantidade' => $fator,
                'comprimento' => null,
                'largura' => $largura,
                'altura' => $altura,
            ];
        }

        // unitario (default)
        $q = isset($input['quantidade']) ? (float) $input['quantidade'] : 0.0;
        if ($q <= 0) {
            throw new \RuntimeException('Quantidade é obrigatória e deve ser maior que zero.', 422);
        }

        return [
            'fator' => $q,
            'quantidade' => $q,
            'comprimento' => null,
            'largura' => null,
            'altura' => null,
        ];
    }

    public function fromOsItem(Produto $produto, OsItem $item): float
    {
        $forma = method_exists($produto, 'formaCalculoEfetiva')
            ? $produto->formaCalculoEfetiva()
            : (string) ($produto->forma_calculo ?? $produto->tipo_medida ?? 'unitario');

        if ($forma === 'metro_linear') {
            $c = (float) ($item->comprimento ?? 0);
            if ($c <= 0) {
                // compat legado
                $c = (float) ($item->quantidade ?? 0);
            }
            return $c;
        }

        if ($forma === 'metro_quadrado') {
            $l = (float) ($item->largura ?? 0);
            $a = (float) ($item->altura ?? 0);
            $f = $l * $a;
            if ($f <= 0) {
                // compat legado
                $f = (float) ($item->quantidade ?? 0);
            }
            return $f;
        }

        return (float) ($item->quantidade ?? 0);
    }
}
