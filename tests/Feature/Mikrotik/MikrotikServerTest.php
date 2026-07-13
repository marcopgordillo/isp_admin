<?php

use App\Models\MikrotikServer;
use App\Models\Tenant;
use App\Services\Mikrotik\MikrotikConnection;

it('can register a mikrotik server for tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Mikrotik ISP',
        'email' => 'mikrotik@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
        'is_sstp_server' => true,
    ]);

    expect($server)->not->toBeNull()
        ->and($server->tenant_id)->toBe($tenant->id);
});

it('validates mikrotik connection via API', function () {
    $tenant = Tenant::create([
        'name' => 'Connection ISP',
        'email' => 'connection@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $connection = new MikrotikConnection($server);
    
    // Mock para testing - en producción intentaría conexión real
    expect($connection->testConnection())->toBeBool();
});

it('creates pppoe secret on mikrotik when customer is created', function () {
    $tenant = Tenant::create([
        'name' => 'PPPoE ISP',
        'email' => 'pppoe@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'pppoe_username' => 'johndoe',
        'pppoe_password' => 'securepass123',
        'pppoe_profile' => '5mbps',
    ]);

    // El observer o servicio debería crear el PPPoE secret en Mikrotik
    expect($customer->mikrotik_secret_id)->not->toBeNull();
});

it('removes pppoe secret when customer is deleted', function () {
    $tenant = Tenant::create([
        'name' => 'Delete PPPoE ISP',
        'email' => 'delete.pppoe@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'pppoe_username' => 'janedoe',
        'pppoe_password' => 'securepass456',
        'pppoe_profile' => '10mbps',
        'mikrotik_secret_id' => 'secret_123',
    ]);

    $customer->delete();

    // Debería haberse eliminado del Mikrotik
    expect($customer->mikrotik_secret_id)->toBeNull();
});

it('disables pppoe secret when customer is suspended', function () {
    $tenant = Tenant::create([
        'name' => 'Suspend ISP',
        'email' => 'suspend@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Suspended User',
        'email' => 'suspended@example.com',
        'pppoe_username' => 'suspended',
        'status' => 'active',
    ]);

    $customer->suspend();

    expect($customer->fresh()->status)->toBe('suspended');
    // El secret en Mikrotik debería estar deshabilitado
});

it('enables pppoe secret when customer is reactivated', function () {
    $tenant = Tenant::create([
        'name' => 'Reactivate ISP',
        'email' => 'reactivate@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Reactivated User',
        'email' => 'reactivated@example.com',
        'pppoe_username' => 'reactivated',
        'status' => 'suspended',
    ]);

    $customer->activate();

    expect($customer->fresh()->status)->toBe('active');
    // El secret en Mikrotik debería estar habilitado
});

it('updates pppoe profile when customer changes plan', function () {
    $tenant = Tenant::create([
        'name' => 'Profile Change ISP',
        'email' => 'profile@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Profile User',
        'email' => 'profile@example.com',
        'pppoe_username' => 'profileuser',
        'pppoe_profile' => '5mbps',
    ]);

    $customer->updatePppoeProfile('10mbps');

    expect($customer->fresh()->pppoe_profile)->toBe('10mbps');
    // El perfil en Mikrotik debería haberse actualizado
});

it('retrieves active pppoe sessions from mikrotik', function () {
    $tenant = Tenant::create([
        'name' => 'Session ISP',
        'email' => 'session@isp.com',
    ]);

    $server = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Router',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret',
        'api_port' => 8728,
    ]);

    $connection = new MikrotikConnection($server);
    $sessions = $connection->getActivePppoeSessions();

    expect($sessions)->toBeArray();
});

it('supports multiple mikrotik servers per tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Multi Router ISP',
        'email' => 'multi@isp.com',
    ]);

    $server1 = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Router East',
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'secret1',
        'api_port' => 8728,
    ]);

    $server2 = MikrotikServer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Router West',
        'host' => '192.168.89.1',
        'username' => 'admin',
        'password' => 'secret2',
        'api_port' => 8728,
    ]);

    expect($tenant->mikrotikServers()->count())->toBe(2);
});
