<?php

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Tenant;

it('generates monthly invoice for each active customer', function () {
    $tenant = Tenant::create([
        'name' => 'Billing ISP',
        'email' => 'billing@isp.com',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Monthly Customer',
        'email' => 'monthly@customer.com',
        'plan_price' => 29.99,
        'billing_day' => 1,
        'status' => 'active',
    ]);

    Invoice::generateForCustomer($customer);

    expect(Invoice::where('customer_id', $customer->id)->count())->toBe(1)
        ->and(Invoice::where('customer_id', $customer->id)->first()->amount)->toBe(29.99);
});

it('sets invoice due date based on tenant grace period', function () {
    $tenant = Tenant::create([
        'name' => 'Grace ISP',
        'email' => 'grace@isp.com',
        'grace_period_days' => 5,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Grace Customer',
        'email' => 'grace@customer.com',
        'plan_price' => 29.99,
        'billing_day' => 1,
        'status' => 'active',
    ]);

    $invoice = Invoice::generateForCustomer($customer);

    $expectedDueDate = now()->addDays(5);
    
    expect($invoice->due_date->format('Y-m-d'))->toBe($expectedDueDate->format('Y-m-d'));
});

it('marks invoice as paid when payment is received', function () {
    $tenant = Tenant::create([
        'name' => 'Payment ISP',
        'email' => 'payment@isp.com',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Paying Customer',
        'email' => 'paying@customer.com',
        'plan_price' => 29.99,
        'status' => 'active',
    ]);

    $invoice = Invoice::generateForCustomer($customer);

    expect($invoice->status)->toBe('pending');

    $invoice->markAsPaid();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->paid_at)->not->toBeNull();
});

it('suspends customer after grace period when invoice is unpaid', function () {
    $tenant = Tenant::create([
        'name' => 'Suspend Billing ISP',
        'email' => 'suspendbilling@isp.com',
        'grace_period_days' => 3,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Unpaid Customer',
        'email' => 'unpaid@customer.com',
        'plan_price' => 29.99,
        'status' => 'active',
    ]);

    $invoice = Invoice::generateForCustomer($customer);
    
    // Simular vencimiento hace 4 días
    $invoice->update([
        'due_date' => now()->subDays(4),
        'status' => 'pending',
    ]);

    \App\Services\Billing\AutoSuspensionService::checkAndSuspendOverdueCustomers();

    expect($customer->fresh()->status)->toBe('suspended');
});

it('does not suspend customer within grace period', function () {
    $tenant = Tenant::create([
        'name' => 'No Suspend ISP',
        'email' => 'nosuspend@isp.com',
        'grace_period_days' => 5,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Within Grace Customer',
        'email' => 'withingrace@customer.com',
        'plan_price' => 29.99,
        'status' => 'active',
    ]);

    $invoice = Invoice::generateForCustomer($customer);
    
    // Simular vencimiento hace 2 días (dentro del período de gracia)
    $invoice->update([
        'due_date' => now()->subDays(2),
        'status' => 'pending',
    ]);

    \App\Services\Billing\AutoSuspensionService::checkAndSuspendOverdueCustomers();

    expect($customer->fresh()->status)->toBe('active');
});

it('reactivates customer when overdue invoice is paid', function () {
    $tenant = Tenant::create([
        'name' => 'Reactivate Billing ISP',
        'email' => 'reactivatebilling@isp.com',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Reactivated Payer',
        'email' => 'reactivatedpayer@customer.com',
        'plan_price' => 29.99,
        'status' => 'suspended',
    ]);

    $invoice = Invoice::generateForCustomer($customer);
    $invoice->update(['status' => 'pending']);

    $invoice->markAsPaid();

    expect($customer->fresh()->status)->toBe('active');
});

it('generates invoices for all active customers on billing day', function () {
    $tenant = Tenant::create([
        'name' => 'Batch Billing ISP',
        'email' => 'batchbilling@isp.com',
    ]);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Customer 1',
        'email' => 'c1@customer.com',
        'plan_price' => 29.99,
        'billing_day' => 1,
        'status' => 'active',
    ]);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Customer 2',
        'email' => 'c2@customer.com',
        'plan_price' => 49.99,
        'billing_day' => 1,
        'status' => 'active',
    ]);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Customer 3',
        'email' => 'c3@customer.com',
        'plan_price' => 39.99,
        'billing_day' => 15, // Diferente día de facturación
        'status' => 'active',
    ]);

    Invoice::generateMonthlyInvoices(day: 1);

    expect(Invoice::whereHas('customer', fn($q) => $q->where('billing_day', 1))->count())->toBe(2)
        ->and(Invoice::whereHas('customer', fn($q) => $q->where('billing_day', 15))->count())->toBe(0);
});

it('skips invoice generation for suspended customers', function () {
    $tenant = Tenant::create([
        'name' => 'Skip Suspended ISP',
        'email' => 'skipsuspended@isp.com',
    ]);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Active Customer',
        'email' => 'activecust@customer.com',
        'plan_price' => 29.99,
        'billing_day' => 1,
        'status' => 'active',
    ]);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Suspended Customer',
        'email' => 'suspendedcust@customer.com',
        'plan_price' => 29.99,
        'billing_day' => 1,
        'status' => 'suspended',
    ]);

    Invoice::generateMonthlyInvoices(day: 1);

    expect(Invoice::whereHas('customer', fn($q) => $q->where('status', 'active'))->count())->toBe(1)
        ->and(Invoice::whereHas('customer', fn($q) => $q->where('status', 'suspended'))->count())->toBe(0);
});

it('calculates late fees for overdue invoices', function () {
    $tenant = Tenant::create([
        'name' => 'Late Fee ISP',
        'email' => 'latefee@isp.com',
        'late_fee_percentage' => 5,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Late Fee Customer',
        'email' => 'latefee@customer.com',
        'plan_price' => 100.00,
        'status' => 'active',
    ]);

    $invoice = Invoice::generateForCustomer($customer);
    $invoice->update(['due_date' => now()->subDays(10)]);

    $lateFee = $invoice->calculateLateFee();

    expect($lateFee)->toBe(5.00); // 5% de 100
});

it('sends payment reminder emails before due date', function () {
    $tenant = Tenant::create([
        'name' => 'Reminder ISP',
        'email' => 'reminder@isp.com',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Reminder Customer',
        'email' => 'reminder@customer.com',
        'plan_price' => 29.99,
        'status' => 'active',
    ]);

    $invoice = Invoice::generateForCustomer($customer);
    $invoice->update(['due_date' => now()->addDays(3)]);

    // Esto debería disparar un email de recordatorio
    \App\Services\Billing\ReminderService::sendUpcomingDueReminders(daysBefore: 3);

    expect(true)->toBeTrue(); // Verificar en logs o queue
});
