<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can create a tenant with a dedicated database schema', function () {
    $tenantData = [
        'name' => 'ISP Demo',
        'email' => 'admin@ispdemo.com',
        'subdomain' => 'isp-demo',
        'plan' => 'basic', // basic (mikrotik) o advanced (mikrotik + olt)
    ];

    // Act: Crear el tenant
    $tenant = Tenant::create($tenantData);

    // Assert: El tenant existe en la tabla central
    expect($tenant)->not->toBeNull();
    expect($tenant->name)->toBe('ISP Demo');
    expect($tenant->subdomain)->toBe('isp-demo');

    // Assert: Se ha creado el esquema en la base de datos
    $schemaName = $tenant->db_name; // Esperamos algo como 'tenant_isp_demo' o similar
    expect($schemaName)->not->toBeNull();

    // Verificar que el esquema existe en PostgreSQL
    $schemaExists = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?", [$schemaName]);
    expect($schemaExists)->not->toBeEmpty();

    // Assert: Podemos escribir datos dentro del contexto del tenant
    // Esto simularía guardar un cliente final dentro del ISP
    $tenant->run(function () use ($tenantData) {
        // Aquí iría la lógica de crear un usuario o configuración inicial dentro del schema
        DB::table('migrations')->exists(); // Verifica que las migraciones corrieron en el schema
    });
});

it('isolates data between tenants', function () {
    $tenant1 = Tenant::create([
        'name' => 'ISP One',
        'email' => 'one@isp.com',
        'subdomain' => 'isp-one',
        'plan' => 'basic',
    ]);

    $tenant2 = Tenant::create([
        'name' => 'ISP Two',
        'email' => 'two@isp.com',
        'subdomain' => 'isp-two',
        'plan' => 'advanced',
    ]);

    // Escribir datos en el tenant 1
    $tenant1->run(function () {
        DB::table('settings')->insert(['key' => 'radius_secret', 'value' => 'secret1']);
    });

    // Escribir datos en el tenant 2
    $tenant2->run(function () {
        DB::table('settings')->insert(['key' => 'radius_secret', 'value' => 'secret2']);
    });

    // Verificar aislamiento
    $tenant1->run(function () {
        $value = DB::table('settings')->where('key', 'radius_secret')->value('value');
        expect($value)->toBe('secret1');
    });

    $tenant2->run(function () {
        $value = DB::table('settings')->where('key', 'radius_secret')->value('value');
        expect($value)->toBe('secret2');
    });
});
