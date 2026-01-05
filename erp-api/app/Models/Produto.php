<?php

namespace App\Models;

class Produto extends BaseModel
{
    protected $fillable = [
        'empresa_id',
        'nome',
        'sku',
        // Produto Vivo
        'custo_base',
        'preco_venda',
        // Novo: cálculo por medida
        'forma_calculo',
        'preco_base',
        'controla_estoque',
        'ativo',

        // compat / legado
        'preco',
        'vendavel',
        'legacy_produto_composto_id',
        'descricao',
        'tipo_medida',
        'largura_padrao',
        'altura_padrao',
        'preco_manual',
        'markup',
        'custo_calculado',
        'preco_final',
        'status',

        // calculados e persistidos
        'custo_total',
        'lucro',
        'margem_percentual',
    ];

    protected $casts = [
        'custo_base' => 'decimal:4',
        'preco_venda' => 'decimal:4',
        'preco_base' => 'decimal:4',
        'custo_total' => 'decimal:4',
        'lucro' => 'decimal:4',
        'margem_percentual' => 'decimal:4',
        'controla_estoque' => 'boolean',
        'ativo' => 'boolean',
        'vendavel' => 'boolean',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Produto $produto) {
            if (! $produto->sku) {
                $empresaId = (int) ($produto->empresa_id ?? 0);
                if ($empresaId > 0) {
                    $produto->sku = self::generateUniqueSku($empresaId);
                }
            }
        });
    }

    public static function generateUniqueSku(int $empresaId): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $digits = random_int(6, 8);
            $min = 10 ** ($digits - 1);
            $max = (10 ** $digits) - 1;
            $sku = 'ART' . (string) random_int($min, $max);

            $exists = self::query()
                ->where('empresa_id', $empresaId)
                ->where('sku', $sku)
                ->exists();

            if (! $exists) {
                return $sku;
            }
        }

        throw new \RuntimeException('Não foi possível gerar um SKU único.', 500);
    }

    public function formaCalculoEfetiva(): string
    {
        $fc = (string) ($this->forma_calculo ?? '');
        if ($fc !== '') {
            return $fc;
        }

        // compat legado
        $tm = (string) ($this->tipo_medida ?? '');
        return $tm !== '' ? $tm : 'unitario';
    }

    public function produtoComponentes()
    {
        return $this->hasMany(ProdutoComponente::class, 'produto_id');
    }

    public function componentes()
    {
        return $this->belongsToMany(Componente::class, 'produto_componentes', 'produto_id', 'componente_id');
    }

    public function materiaisPivot()
    {
        return $this->hasMany(ProdutoMaterial::class, 'produto_id');
    }

    public function insumosPivot()
    {
        return $this->hasMany(ProdutoInsumo::class, 'produto_id');
    }

    public function processosProdutivosPivot()
    {
        return $this->hasMany(ProdutoProcessoProdutivo::class, 'produto_id');
    }

    public function acabamentosPivot()
    {
        return $this->hasMany(ProdutoAcabamento::class, 'produto_id');
    }

    public function maoObraPivot()
    {
        return $this->hasMany(ProdutoMaoObra::class, 'produto_id');
    }

    public function equipamentosPivot()
    {
        return $this->hasMany(ProdutoEquipamento::class, 'produto_id');
    }

    public function comprasItensPivot()
    {
        return $this->hasMany(ProdutoCompraItem::class, 'produto_id');
    }

    public function insumos()
    {
        return $this->belongsToMany(
            Insumo::class,
            'produto_insumos',
            'produto_id',
            'insumo_id'
        )
            ->withPivot(['empresa_id', 'quantidade_base'])
            ->withTimestamps();
    }

    public function materiais()
    {
        return $this->belongsToMany(
            Produto::class,
            'produto_materiais',
            'produto_id',
            // coluna nova do Produto Vivo
            'material_id'
        )
            ->withPivot(['empresa_id', 'quantidade', 'quantidade_base'])
            ->withTimestamps();
    }

    public function processosProdutivos()
    {
        return $this->belongsToMany(
            ProcessoProdutivo::class,
            'produto_processos_produtivos',
            'produto_id',
            'processo_produtivo_id'
        )
            ->withPivot(['empresa_id', 'quantidade_base'])
            ->withTimestamps();
    }

    public function acabamentos()
    {
        return $this->belongsToMany(
            Acabamento::class,
            'produto_acabamentos',
            'produto_id',
            'acabamento_id'
        )
            ->withPivot(['empresa_id', 'quantidade_base'])
            ->withTimestamps();
    }

    public function maoObra()
    {
        return $this->belongsToMany(
            CustoMaoObra::class,
            'produto_mao_obra',
            'produto_id',
            'custo_mao_obra_id'
        )
            ->withPivot(['empresa_id', 'minutos_base'])
            ->withTimestamps();
    }

    public function equipamentos()
    {
        return $this->belongsToMany(
            Equipamento::class,
            'produto_equipamentos',
            'produto_id',
            'equipamento_id'
        )
            ->withPivot(['empresa_id', 'quantidade_base'])
            ->withTimestamps();
    }
}
