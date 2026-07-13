<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MikrotikServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'host',
        'username',
        'password',
        'port',
        'is_connected',
        'last_sync',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'last_sync' => 'datetime',
        'port' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pppoeUsers()
    {
        return $this->hasMany(PppoeUser::class);
    }
}
