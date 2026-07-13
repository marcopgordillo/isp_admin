<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('can create a new tenant with unique schema', function () {
    $tenant = Tenant::create([
        'name' => 'ISP Test',
        'email' => 'test@isp.com',
        'subdomain' => 'test-isp',
    ]);

    expect($tenant)->not->toBeNull()
        ->and($tenant->id)->not->toBeNull()
        ->and($tenant->db_name)->toStartWith('tenant_test-isp_');
});

it('generates unique db_name from tenant subdomain', function () {
    $tenant = Tenant::create([
        'name' => 'My ISP Company',
        'email' => 'info@myisp.com',
        'subdomain' => 'my-isp',
    ]);

    expect($tenant->db_name)->toMatchRegex('/^tenant_my-isp_[a-zA-Z0-9]+$/');
});

it('creates database schema for tenant on creation', function () {
    $tenant = Tenant::create([
        'name' => 'Schema Test ISP',
        'email' => 'schema@test.com',
        'subdomain' => 'schema-test',
    ]);

    $schemaName = $tenant->db_name;
    
    $exists = DB::select("SELECT EXISTS (
        SELECT 1 FROM information_schema.schemata 
        WHERE schema_name = ?
    ) as exists", [$schemaName]);

    expect($exists[0]->exists)->toBe(true);
});

it('drops tenant schema when tenant is deleted', function () {
    $tenant = Tenant::create([
        'name' => 'Delete Test ISP',
        'email' => 'delete@test.com',
        'subdomain' => 'delete-test',
    ]);

    $schemaName = $tenant->db_name;
    $tenantId = $tenant->id;

    $tenant->delete();

    $exists = DB::select("SELECT EXISTS (
        SELECT 1 FROM information_schema.schemata 
        WHERE schema_name = ?
    ) as exists", [$schemaName]);

    expect($exists[0]->exists)->toBe(false);
});

it('prevents duplicate subdomains', function () {
    Tenant::create([
        'name' => 'Duplicate ISP',
        'email' => 'dup1@isp.com',
        'subdomain' => 'duplicate-isp',
    ]);

    expect(fn() => Tenant::create([
        'name' => 'Duplicate ISP 2',
        'email' => 'dup2@isp.com',
        'subdomain' => 'duplicate-isp',
    ]))->toThrow();
});

it('switches to tenant schema context correctly', function () {
    $tenant = Tenant::create([
        'name' => 'Context Test ISP',
        'email' => 'context@test.com',
        'subdomain' => 'context-test',
    ]);

    $result = $tenant->run(function () {
        return DB::select('SELECT current_schema() as schema');
    });

    expect($result[0]->schema)->toBe($tenant->db_name);
});
