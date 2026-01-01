<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsStatus extends Model
{
    protected $table = 'os_status';

    protected $fillable = [
        'nome',
        'ordem',
        'ativo',
    ];
}
