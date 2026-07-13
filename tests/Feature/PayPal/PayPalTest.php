<?php

use App\Models\Tenant;
use App\Services\PayPal\PayPalService;

it('creates paypal order for invoice payment', function () {
    $tenant = Tenant::create([
        'name' => 'PayPal ISP',
        'email' => 'paypal@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'PayPal Customer',
        'email' => 'paypal@customer.com',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'pending',
    ]);

    $paypalService = app(PayPalService::class);
    $order = $paypalService->createOrder($invoice);

    expect($order)->not->toBeNull()
        ->and($order['id'])->not->toBeNull()
        ->and($order['status'])->toBe('CREATED');
});

it('handles paypal webhook for successful payment', function () {
    $tenant = Tenant::create([
        'name' => 'Webhook ISP',
        'email' => 'webhook@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Webhook Customer',
        'email' => 'webhook@customer.com',
        'status' => 'suspended',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'pending',
    ]);

    $webhookPayload = [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'custom_id' => (string) $invoice->id,
            'status' => 'COMPLETED',
            'amount' => [
                'value' => '29.99',
                'currency_code' => 'USD',
            ],
        ],
    ];

    $paypalService = app(PayPalService::class);
    $paypalService->handleWebhook($webhookPayload);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($customer->fresh()->status)->toBe('active');
});

it('ignores paypal webhook for already paid invoice', function () {
    $tenant = Tenant::create([
        'name' => 'Duplicate Webhook ISP',
        'email' => 'duplicatewebhook@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Duplicate Customer',
        'email' => 'duplicate@customer.com',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $originalPaidAt = $invoice->paid_at;

    $webhookPayload = [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'custom_id' => (string) $invoice->id,
            'status' => 'COMPLETED',
        ],
    ];

    $paypalService = app(PayPalService::class);
    $paypalService->handleWebhook($webhookPayload);

    // No debería modificar una factura ya pagada
    expect($invoice->fresh()->paid_at)->toBe($originalPaidAt);
});

it('handles paypal webhook for failed payment', function () {
    $tenant = Tenant::create([
        'name' => 'Failed Payment ISP',
        'email' => 'failedpayment@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Failed Customer',
        'email' => 'failed@customer.com',
        'status' => 'active',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'pending',
    ]);

    $webhookPayload = [
        'event_type' => 'PAYMENT.CAPTURE.DENIED',
        'resource' => [
            'custom_id' => (string) $invoice->id,
            'status' => 'DENIED',
        ],
    ];

    $paypalService = app(PayPalService::class);
    $paypalService->handleWebhook($webhookPayload);

    expect($invoice->fresh()->status)->toBe('failed');
});

it('verifies paypal webhook signature', function () {
    $validSignature = 'valid-signature-hash';
    $invalidSignature = 'invalid-signature-hash';
    
    $webhookPayload = ['event_type' => 'TEST'];
    
    $paypalService = app(PayPalService::class);
    
    expect($paypalService->verifyWebhookSignature($webhookPayload, $validSignature))->toBeTrue()
        ->and($paypalService->verifyWebhookSignature($webhookPayload, $invalidSignature))->toBeFalse();
});

it('generates paypal checkout url for invoice', function () {
    $tenant = Tenant::create([
        'name' => 'Checkout ISP',
        'email' => 'checkout@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Checkout Customer',
        'email' => 'checkout@customer.com',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'pending',
    ]);

    $paypalService = app(PayPalService::class);
    $checkoutUrl = $paypalService->getCheckoutUrl($invoice);

    expect($checkoutUrl)->not->toBeNull()
        ->and($checkoutUrl)->toStartWith('https://www.paypal.com/checkoutnow?token=');
});

it('stores paypal transaction id on successful payment', function () {
    $tenant = Tenant::create([
        'name' => 'Transaction ISP',
        'email' => 'transaction@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Transaction Customer',
        'email' => 'transaction@customer.com',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'pending',
    ]);

    $webhookPayload = [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'custom_id' => (string) $invoice->id,
            'status' => 'COMPLETED',
            'id' => 'PAYPAL-TRANSACTION-ID-12345',
        ],
    ];

    $paypalService = app(PayPalService::class);
    $paypalService->handleWebhook($webhookPayload);

    expect($invoice->fresh()->paypal_transaction_id)->toBe('PAYPAL-TRANSACTION-ID-12345');
});

it('handles refund via paypal', function () {
    $tenant = Tenant::create([
        'name' => 'Refund ISP',
        'email' => 'refund@isp.com',
    ]);

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Refund Customer',
        'email' => 'refund@customer.com',
    ]);

    $invoice = \App\Models\Invoice::create([
        'customer_id' => $customer->id,
        'amount' => 29.99,
        'status' => 'paid',
        'paypal_transaction_id' => 'PAYPAL-TRANSACTION-ID-12345',
    ]);

    $paypalService = app(PayPalService::class);
    $refundResult = $paypalService->refundPayment($invoice);

    expect($refundResult)->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('refunded');
});
