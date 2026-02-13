<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhitelistIp extends Model
{
    use HasFactory;

    /**
     * O nome da tabela associada ao modelo.
     */
    protected $table = 'whitelist_ips';

    /**
     * Os atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'ip',
        'label',
        'is_active',
    ];

    /**
     * Casts para garantir que o status seja sempre booleano.
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];
}