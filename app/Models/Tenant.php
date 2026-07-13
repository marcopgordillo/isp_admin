<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subdomain',
        'plan',
        'db_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model and handle schema creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // Generar nombre único para el schema
            if (empty($tenant->db_name)) {
                $tenant->db_name = 'tenant_' . Str::slug($tenant->subdomain) . '_' . Str::random(8);
            }
            
            // Generar subdomain si no existe
            if (empty($tenant->subdomain)) {
                $tenant->subdomain = Str::slug($tenant->name);
            }
        });

        static::created(function ($tenant) {
            // Crear el schema en PostgreSQL
            $tenant->createSchema();
            
            // Ejecutar migraciones en el schema del tenant
            $tenant->runMigrations();
        });

        static::deleting(function ($tenant) {
            // Eliminar el schema cuando se elimina el tenant
            $tenant->dropSchema();
        });
    }

    /**
     * Crear el schema en la base de datos
     */
    public function createSchema(): void
    {
        $schemaName = $this->db_name;
        
        // Verificar si el schema ya existe
        $exists = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?", [$schemaName]);
        
        if (empty($exists)) {
            DB::statement("CREATE SCHEMA \"$schemaName\"");
        }
    }

    /**
     * Eliminar el schema de la base de datos
     */
    public function dropSchema(): void
    {
        $schemaName = $this->db_name;
        DB::statement("DROP SCHEMA IF EXISTS \"$schemaName\" CASCADE");
    }

    /**
     * Ejecutar migraciones en el schema del tenant
     */
    public function runMigrations(): void
    {
        $this->run(function () {
            // Aquí se ejecutarían las migraciones específicas del tenant
            // Por ahora creamos una tabla básica de configuración
            DB::statement('CREATE TABLE IF NOT EXISTS settings (
                id SERIAL PRIMARY KEY,
                key VARCHAR(255) UNIQUE NOT NULL,
                value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
            
            // Tabla de migraciones para tracking
            DB::statement('CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            )');
        });
    }

    /**
     * Ejecutar callback dentro del contexto del schema del tenant
     */
    public function run(callable $callback): mixed
    {
        $originalSchema = DB::getConfig('search_path') ?? 'public';
        
        try {
            // Cambiar al schema del tenant
            DB::statement("SET search_path TO \"{$this->db_name}\"");
            
            // Ejecutar el callback
            return $callback();
        } finally {
            // Restaurar schema original
            DB::statement("SET search_path TO \"$originalSchema\"");
        }
    }

    /**
     * Relación con servidores Mikrotik
     */
    public function mikrotikServers()
    {
        return $this->hasMany(MikrotikServer::class);
    }

    /**
     * Relación con dispositivos OLT
     */
    public function oltDevices()
    {
        return $this->hasMany(OltDevice::class);
    }

    /**
     * Relación con suscripciones
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Verificar si el plan incluye control de OLT
     */
    public function hasAdvancedPlan(): bool
    {
        return $this->plan === 'advanced';
    }

    /**
     * Verificar si el plan es básico (solo Mikrotik)
     */
    public function hasBasicPlan(): bool
    {
        return $this->plan === 'basic';
    }
}
