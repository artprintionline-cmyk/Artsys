<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected $fillable = [
        'empresa_id',
        'financeiro_lancamento_id',
        'metodo',
        'status',
        'payment_id',
        'qr_code_base64',
        'qr_code_text',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function lancamento()
    {
        return $this->belongsTo(FinanceiroLancamento::class, 'financeiro_lancamento_id');
    }
}
