<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->uses(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeBool', function () {
    return $this->toBeBool();
});

expect()->extend('toBeFloat', function () {
    return $this->toBeFloat();
});

expect()->extend('toStartWith', function ($needle) {
    if (! is_string($this->value)) {
        \PHPUnit\Framework\Assert::fail('Value is not a string.');
    }

    return $this->toBeStringStartingWith($needle);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createTestTenant(string $name = 'Test ISP', string $email = 'test@isp.com')
{
    return \App\Models\Tenant::create([
        'name' => $name,
        'email' => $email,
        'domain' => str($name)->slug().'.example.com',
    ]);
}

function createTestCustomer(array $attributes = [])
{
    $tenant = $attributes['tenant_id'] ?? createTestTenant()->id;
    
    return \App\Models\Customer::create(array_merge([
        'tenant_id' => $tenant,
        'name' => 'Test Customer',
        'email' => fake()->unique()->safeEmail(),
        'status' => 'active',
    ], $attributes));
}
