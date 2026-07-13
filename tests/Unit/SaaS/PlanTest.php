<?php

use App\Models\Plan;
use App\Models\User;

it('has required fillable fields', function () {
    $plan = Plan::factory()->make();

    expect($plan->name)->not->toBeNull()
        ->and($plan->slug)->not->toBeNull()
        ->and($plan->price)->not->toBeNull();
});

it('casts price to float', function () {
    $plan = Plan::factory()->make(['price' => '29.99']);

    expect($plan->price)->toBeFloat()
        ->and($plan->price)->toBe(29.99);
});

it('casts features to array', function () {
    $plan = Plan::factory()->make([
        'features' => ['Feature 1', 'Feature 2'],
    ]);

    expect($plan->features)->toBeArray()
        ->and($plan->features)->toHaveCount(2);
});

it('belongs to many tenants', function () {
    $plan = Plan::factory()->create();
    
    $tenant1 = \App\Models\Tenant::factory()->create(['plan_id' => $plan->id]);
    $tenant2 = \App\Models\Tenant::factory()->create(['plan_id' => $plan->id]);

    expect($plan->tenants)->toHaveCount(2);
});

it('has scope for enabled mikrotik module', function () {
    Plan::factory()->create(['mikrotik_enabled' => true]);
    Plan::factory()->create(['mikrotik_enabled' => false]);

    $enabledPlans = Plan::mikrotikEnabled()->count();

    expect($enabledPlans)->toBe(1);
});

it('has scope for enabled olt module', function () {
    Plan::factory()->create(['olt_enabled' => true]);
    Plan::factory()->create(['olt_enabled' => false]);

    $enabledPlans = Plan::oltEnabled()->count();

    expect($enabledPlans)->toBe(1);
});
