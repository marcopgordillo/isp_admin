<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OltDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'brand', // huawei, zte, fiberhome, etc.
        'host',
        'username',
        'password',
        'port',
        'protocol', // snmp, telnet, ssh
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

    public function onus()
    {
        return $this->hasMany(Onu::class);
    }
}
