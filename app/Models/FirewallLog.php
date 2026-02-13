<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirewallLog extends Model
{
    use HasFactory;

    /**
     * O nome da tabela associada ao modelo.
     */
    protected $table = 'firewall_logs';

    /**
     * Os atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'ip',
        'url',
        'user_agent',
    ];
}