<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PppoeUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'mikrotik_server_id',
        'client_id',
        'username',
        'password',
        'profile',
        'ip_address',
        'is_active',
        'is_blocked',
        'last_connection',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'last_connection' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mikrotikServer()
    {
        return $this->belongsTo(MikrotikServer::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function block(): void
    {
        $this->update(['is_blocked' => true]);
        // Aquí se llamará al servicio para bloquear en Mikrotik
    }

    public function unblock(): void
    {
        $this->update(['is_blocked' => false]);
        // Aquí se llamará al servicio para desbloquear en Mikrotik
    }
}
