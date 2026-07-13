<?php

use App\Models\OltDevice;
use App\Models\Tenant;
use App\Services\OLT\OltConnection;

it('can register an olt device for tenant', function () {
    $tenant = Tenant::create([
        'name' => 'OLT ISP',
        'email' => 'olt@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Huawei MA5800',
        'brand' => 'huawei',
        'model' => 'MA5800-X17',
        'ip_address' => '192.168.100.1',
        'username' => 'root',
        'password' => 'admin',
        'port' => 23,
        'connection_type' => 'telnet',
    ]);

    expect($olt)->not->toBeNull()
        ->and($olt->tenant_id)->toBe($tenant->id);
});

it('supports multiple olt brands (huawei, zte, fiberhome)', function () {
    $tenant = Tenant::create([
        'name' => 'Multi Brand ISP',
        'email' => 'multibrand@isp.com',
    ]);

    $huawei = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Huawei OLT',
        'brand' => 'huawei',
        'model' => 'MA5800',
        'ip_address' => '192.168.100.1',
    ]);

    $zte = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'ZTE OLT',
        'brand' => 'zte',
        'model' => 'C300',
        'ip_address' => '192.168.100.2',
    ]);

    $fiberhome = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'FiberHome OLT',
        'brand' => 'fiberhome',
        'model' => 'AN5516',
        'ip_address' => '192.168.100.3',
    ]);

    expect($tenant->oltDevices()->count())->toBe(3)
        ->and($tenant->oltDevices()->where('brand', 'huawei')->count())->toBe(1)
        ->and($tenant->oltDevices()->where('brand', 'zte')->count())->toBe(1)
        ->and($tenant->oltDevices()->where('brand', 'fiberhome')->count())->toBe(1);
});

it('registers onu with serial number on olt', function () {
    $tenant = Tenant::create([
        'name' => 'ONU Register ISP',
        'email' => 'onuregister@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'ONU Customer',
        'email' => 'onu@customer.com',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC12345678',
        'pon_port' => '0/1/0',
        'onu_id' => 1,
    ]);

    expect($onu)->not->toBeNull()
        ->and($onu->serial_number)->toBe('HWTC12345678')
        ->and($onu->olt_id)->toBe($olt->id);
});

it('authorizes onu on olt using serial number', function () {
    $tenant = Tenant::create([
        'name' => 'Authorize ISP',
        'email' => 'authorize@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC87654321',
        'pon_port' => '0/2/0',
        'status' => 'pending',
    ]);

    $connection = new OltConnection($olt);
    $result = $connection->authorizeOnu($onu);

    expect($result)->toBeTrue()
        ->and($onu->fresh()->status)->toBe('authorized');
});

it('blocks onu on olt when customer is suspended', function () {
    $tenant = Tenant::create([
        'name' => 'Block ISP',
        'email' => 'block@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Blocked Customer',
        'email' => 'blocked@customer.com',
        'status' => 'active',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC11111111',
        'pon_port' => '0/3/0',
        'onu_id' => 5,
        'status' => 'active',
    ]);

    $customer->suspend();

    expect($onu->fresh()->status)->toBe('blocked');
});

it('unblocks onu when customer payment is confirmed', function () {
    $tenant = Tenant::create([
        'name' => 'Unblock ISP',
        'email' => 'unblock@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Unblocked Customer',
        'email' => 'unblocked@customer.com',
        'status' => 'suspended',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC22222222',
        'pon_port' => '0/4/0',
        'onu_id' => 10,
        'status' => 'blocked',
    ]);

    $customer->activate();

    expect($onu->fresh()->status)->toBe('active');
});

it('reads onu optical power from olt', function () {
    $tenant = Tenant::create([
        'name' => 'Optical ISP',
        'email' => 'optical@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC33333333',
        'pon_port' => '0/5/0',
        'onu_id' => 15,
    ]);

    $connection = new OltConnection($olt);
    $opticalPower = $connection->getOnuOpticalPower($onu);

    expect($opticalPower)->toBeFloat()
        ->and($opticalPower)->toBeLessThan(0) // dBm es negativo
        ->and($opticalPower)->toBeGreaterThan(-30); // Límite típico inferior
});

it('alerts when optical power is below threshold', function () {
    $tenant = Tenant::create([
        'name' => 'Alert ISP',
        'email' => 'alert@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC44444444',
        'pon_port' => '0/6/0',
        'onu_id' => 20,
    ]);

    // Simular lectura de potencia baja
    $onu->update(['optical_power' => -28.5]);

    expect($onu->hasLowSignal())->toBeTrue();
});

it('deletes onu from olt when customer is removed', function () {
    $tenant = Tenant::create([
        'name' => 'Delete ONU ISP',
        'email' => 'deleteonu@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Delete Customer',
        'email' => 'delete@customer.com',
    ]);

    $onu = \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC55555555',
        'pon_port' => '0/7/0',
        'onu_id' => 25,
    ]);

    $onuId = $onu->id;
    $customer->delete();

    expect(\App\Models\Onu::find($onuId))->toBeNull();
});

it('lists all onus per pon port', function () {
    $tenant = Tenant::create([
        'name' => 'List ONU ISP',
        'email' => 'listonu@isp.com',
    ]);

    $olt = OltDevice::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main OLT',
        'brand' => 'huawei',
        'ip_address' => '192.168.100.1',
    ]);

    \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC61111111',
        'pon_port' => '0/1/0',
        'onu_id' => 1,
    ]);

    \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC62222222',
        'pon_port' => '0/1/0',
        'onu_id' => 2,
    ]);

    \App\Models\Onu::create([
        'tenant_id' => $tenant->id,
        'olt_id' => $olt->id,
        'serial_number' => 'HWTC63333333',
        'pon_port' => '0/2/0',
        'onu_id' => 1,
    ]);

    $onusOnPort01 = \App\Models\Onu::where('olt_id', $olt->id)
        ->where('pon_port', '0/1/0')
        ->count();

    expect($onusOnPort01)->toBe(2);
});
