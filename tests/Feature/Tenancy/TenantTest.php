<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

it('can create a new tenant with unique schema', function () {
    $tenant = Tenant::create([
        'name' => 'ISP Test',
        'email' => 'test@isp.com',
        'domain' => 'test.isp.com',
    ]);

    expect($tenant)->not->toBeNull()
        ->and($tenant->id)->not->toBeNull()
        ->and($tenant->schema)->toBe('isp_test');
});

it('generates unique schema name from tenant name', function () {
    $tenant = Tenant::create([
        'name' => 'My ISP Company',
        'email' => 'info@myisp.com',
        'domain' => 'myisp.com',
    ]);

    expect($tenant->schema)->toMatchRegex('/^my_isp_company_[a-z0-9]+$/');
});

it('creates database schema for tenant on creation', function () {
    $tenant = Tenant::create([
        'name' => 'Schema Test ISP',
        'email' => 'schema@test.com',
        'domain' => 'schema.test.com',
    ]);

    $schemaName = $tenant->schema;
    
    $exists = DB::select("SELECT EXISTS (
        SELECT 1 FROM information_schema.schemata 
        WHERE schema_name = '{$schemaName}'
    ) as exists");

    expect($exists[0]->exists)->toBe(true);
});

it('drops tenant schema when tenant is deleted', function () {
    $tenant = Tenant::create([
        'name' => 'Delete Test ISP',
        'email' => 'delete@test.com',
        'domain' => 'delete.test.com',
    ]);

    $schemaName = $tenant->schema;
    $tenantId = $tenant->id;

    $tenant->delete();

    $exists = DB::select("SELECT EXISTS (
        SELECT 1 FROM information_schema.schemata 
        WHERE schema_name = '{$schemaName}'
    ) as exists");

    expect($exists[0]->exists)->toBe(false);
});

it('prevents duplicate schema names', function () {
    Tenant::create([
        'name' => 'Duplicate ISP',
        'email' => 'dup1@isp.com',
        'domain' => 'dup1.isp.com',
    ]);

    expect(fn() => Tenant::create([
        'name' => 'Duplicate ISP',
        'email' => 'dup2@isp.com',
        'domain' => 'dup2.isp.com',
    ]))->toThrow();
});

it('switches to tenant schema context correctly', function () {
    $tenant = Tenant::create([
        'name' => 'Context Test ISP',
        'email' => 'context@test.com',
        'domain' => 'context.test.com',
    ]);

    tenancy()->initialize($tenant);

    expect(tenancy()->getTenant())->toBe($tenant)
        ->and(tenancy()->getTenant()->schema)->toBe($tenant->schema);

    tenancy()->end();
});
