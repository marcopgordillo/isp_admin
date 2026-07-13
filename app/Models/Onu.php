<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Onu extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'olt_device_id',
        'client_id',
        'serial_number',
        'pon_port',
        'ont_index',
        'description',
        'status', // active, inactive, blocked
        'optical_power',
        'last_sync',
    ];

    protected $casts = [
        'last_sync' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function oltDevice()
    {
        return $this->belongsTo(OltDevice::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function block(): void
    {
        $this->update(['status' => 'blocked']);
        // Aquí se llamará al servicio para bloquear en OLT
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
        // Aquí se llamará al servicio para activar en OLT
    }
}
