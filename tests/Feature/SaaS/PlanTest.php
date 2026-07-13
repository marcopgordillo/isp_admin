<?php

use App\Models\Plan;
use App\Models\Tenant;

it('has basic and advanced plans by default', function () {
    $basicPlan = Plan::where('slug', 'basic')->first();
    $advancedPlan = Plan::where('slug', 'advanced')->first();

    expect($basicPlan)->not->toBeNull()
        ->and($basicPlan->name)->toBe('Plan Básico')
        ->and($basicPlan->features)->toContain('Mikrotik PPPoE Control');

    expect($advancedPlan)->not->toBeNull()
        ->and($advancedPlan->name)->toBe('Plan Avanzado')
        ->and($advancedPlan->features)->toContain('Mikrotik PPPoE Control')
        ->and($advancedPlan->features)->toContain('OLT Management');
});

it('creates tenant with selected plan during registration', function () {
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan',
        'price' => 29.99,
        'currency' => 'USD',
        'billing_cycle' => 'monthly',
        'features' => ['Feature 1', 'Feature 2'],
        'mikrotik_enabled' => true,
        'olt_enabled' => false,
    ]);

    $tenant = Tenant::create([
        'name' => 'ISP with Plan',
        'email' => 'isp@plan.com',
        'domain' => 'isp.plan.com',
        'plan_id' => $plan->id,
    ]);

    expect($tenant->plan_id)->toBe($plan->id)
        ->and($tenant->plan)->toBe($plan);
});

it('enables mikrotik module only for plans that include it', function () {
    $basicPlan = Plan::create([
        'name' => 'Basic Only',
        'slug' => 'basic-only',
        'price' => 19.99,
        'mikrotik_enabled' => true,
        'olt_enabled' => false,
    ]);

    $tenant = Tenant::create([
        'name' => 'Basic ISP',
        'email' => 'basic@isp.com',
        'plan_id' => $basicPlan->id,
    ]);

    expect($tenant->hasModule('mikrotik'))->toBeTrue()
        ->and($tenant->hasModule('olt'))->toBeFalse();
});

it('enables olt module only for advanced plans', function () {
    $advancedPlan = Plan::create([
        'name' => 'Advanced Full',
        'slug' => 'advanced-full',
        'price' => 49.99,
        'mikrotik_enabled' => true,
        'olt_enabled' => true,
    ]);

    $tenant = Tenant::create([
        'name' => 'Advanced ISP',
        'email' => 'advanced@isp.com',
        'plan_id' => $advancedPlan->id,
    ]);

    expect($tenant->hasModule('mikrotik'))->toBeTrue()
        ->and($tenant->hasModule('olt'))->toBeTrue();
});

it('allows plan upgrade for existing tenant', function () {
    $basicPlan = Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price' => 19.99,
        'mikrotik_enabled' => true,
        'olt_enabled' => false,
    ]);

    $advancedPlan = Plan::create([
        'name' => 'Advanced',
        'slug' => 'advanced',
        'price' => 49.99,
        'mikrotik_enabled' => true,
        'olt_enabled' => true,
    ]);

    $tenant = Tenant::create([
        'name' => 'Upgrade ISP',
        'email' => 'upgrade@isp.com',
        'plan_id' => $basicPlan->id,
    ]);

    $tenant->upgradeTo($advancedPlan);

    expect($tenant->fresh()->plan_id)->toBe($advancedPlan->id)
        ->and($tenant->fresh()->hasModule('olt'))->toBeTrue();
});

it('calculates prorated amount when upgrading plan mid-cycle', function () {
    $basicPlan = Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price' => 20.00,
        'billing_cycle' => 'monthly',
    ]);

    $advancedPlan = Plan::create([
        'name' => 'Advanced',
        'slug' => 'advanced',
        'price' => 50.00,
        'billing_cycle' => 'monthly',
    ]);

    $tenant = Tenant::create([
        'name' => 'Prorate ISP',
        'email' => 'prorate@isp.com',
        'plan_id' => $basicPlan->id,
    ]);

    // Simular mitad del ciclo de facturación
    $tenant->subscription->current_period_start = now()->subDays(15);
    $tenant->subscription->current_period_end = now()->addDays(15);

    $proratedAmount = $tenant->calculateProratedUpgrade($advancedPlan);

    expect($proratedAmount)->toBeFloat()
        ->and($proratedAmount)->toBeGreaterThan(0)
        ->and($proratedAmount)->toBeLessThan(50.00);
});
