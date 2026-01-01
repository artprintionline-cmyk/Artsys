<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsHistorico extends Model
{
    protected $table = 'os_historico';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'usuario_id',
        'status_anterior',
        'status_novo',
        'observacao',
    ];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }
}
