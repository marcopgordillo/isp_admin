<?php

use App\Models\Tenant;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create plans with specific features', function () {
    $basicPlan = Plan::create([
        'name' => 'Basic PPPoE',
        'slug' => 'basic-pppoe',
        'price' => 29.99,
        'features' => ['mikrotik_control' => true, 'olt_control' => false],
    ]);

    $advancedPlan = Plan::create([
        'name' => 'Advanced OLT',
        'slug' => 'advanced-olt',
        'price' => 59.99,
        'features' => ['mikrotik_control' => true, 'olt_control' => true],
    ]);

    expect($basicPlan->hasFeature('mikrotik_control'))->toBeTrue();
    expect($basicPlan->hasFeature('olt_control'))->toBeFalse();
    expect($advancedPlan->hasFeature('olt_control'))->toBeTrue();
});

it('allows a tenant to subscribe to a plan', function () {
    $tenant = Tenant::create([
        'name' => 'Test ISP',
        'email' => 'test@isp.com',
        'subdomain' => 'test-isp',
    ]);

    $plan = Plan::create([
        'name' => 'Basic Plan',
        'slug' => 'basic',
        'price' => 10.00,
        'features' => [],
    ]);

    $subscription = $tenant->subscribe($plan, 'trial');

    expect($subscription)->toBeInstanceOf(Subscription::class);
    expect($subscription->plan_id)->toBe($plan->id);
    expect($subscription->status)->toBe('trial');
    expect($tenant->fresh()->activeSubscription)->not->toBeNull();
});

it('prevents subscribing to a plan if already subscribed', function () {
    $tenant = Tenant::create([
        'name' => 'Test ISP',
        'email' => 'test@isp.com',
        'subdomain' => 'test-isp-unique',
    ]);

    $plan = Plan::create(['name' => 'Plan', 'slug' => 'plan-unique', 'price' => 10, 'features' => []]);

    $tenant->subscribe($plan, 'active');

    expect(fn() => $tenant->subscribe($plan, 'active'))
        ->toThrow(Exception::class); // O una excepción específica como ActiveSubscriptionException
});

it('correctly identifies if tenant has advanced module access', function () {
    $tenant = Tenant::create(['name' => 'ISP', 'email' => 'i@sp.com', 'subdomain' => 'isp-mod']);
    $basic = Plan::create(['name' => 'Basic', 'slug' => 'basic-mod', 'price' => 1, 'features' => ['olt_control' => false]]);
    $advanced = Plan::create(['name' => 'Adv', 'slug' => 'adv-mod', 'price' => 2, 'features' => ['olt_control' => true]]);

    $tenant->subscribe($basic, 'active');
    expect($tenant->hasModuleAccess('olt_control'))->toBeFalse();

    // Actualizar suscripción (simulado)
    $tenant->activeSubscription->update(['plan_id' => $advanced->id]);
    
    // Recargar relación si es necesario o probar el método directamente
    expect($tenant->fresh()->hasModuleAccess('olt_control'))->toBeTrue();
});