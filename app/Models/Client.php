<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'document_number',
        'address',
        'city',
        'status', // active, suspended, inactive
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pppoeUser()
    {
        return $this->hasOne(PppoeUser::class);
    }

    public function onu()
    {
        return $this->hasOne(Onu::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
        
        // Suspender servicios asociados
        if ($this->pppoeUser) {
            $this->pppoeUser->block();
        }
        
        if ($this->onu) {
            $this->onu->block();
        }
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
        
        // Activar servicios asociados
        if ($this->pppoeUser) {
            $this->pppoeUser->unblock();
        }
        
        if ($this->onu) {
            $this->onu->activate();
        }
    }
}
