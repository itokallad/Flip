<?php

use App\Jobs\ProcessOrderJob;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('can create a provisioning order', function () {
    Queue::fake();

    $payload = [
        'orderId' => 'order_12345',
    ];

    $response = $this->postJson('/api/orders', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
        'orderId',
        'status',
    ])
        ->assertJsonFragment([
        'orderId' => 'order_12345',
        'status' => 'RECEIVED',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => 'order_12345',
        'status' => 'RECEIVED',
    ]);

    $this->assertDatabaseHas('order_status_transitions', [
        'status' => 'RECEIVED',
    ]);

    Queue::assertPushed(ProcessOrderJob::class , function ($job) {
            return $job->order->id === 'order_12345';
        }
        );
    });

test('idempotent order creation does not dispatch duplicate jobs', function () {
    Queue::fake();

    $payload = [
        'orderId' => 'order_idem_999',
    ];

    // First request
    $this->postJson('/api/orders', $payload)->assertStatus(201);

    // Validate job dispatched once
    Queue::assertPushed(ProcessOrderJob::class , 1);

    // Second request with same payload
    $this->postJson('/api/orders', $payload)->assertStatus(200);

    // Validate job was NOT dispatched again
    Queue::assertPushed(ProcessOrderJob::class , 1);
});

test('can get an order with its history', function () {
    Queue::fake();

    $order = Order::create(['id' => 'test_123', 'status' => 'RECEIVED']);

    Http::fake([
        '*/api/provider/submit' => Http::response(['providerOrderId' => 'prov_123', 'status' => 'PENDING'], 200),
        '*/api/provider/status/*' => Http::response(['status' => 'COMPLETED'], 200),
    ]);

    // Manually run the job to transition statuses after the initial RECEIVED transition is created
    (new ProcessOrderJob($order))->handle();

    $response = $this->getJson("/api/orders/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
        'status',
        'history' => [
            '*' => [
                'status',
                'timestamp',
            ],
        ],
    ])
        ->assertJsonFragment(['status' => 'COMPLETED'])
        ->assertJsonPath('history.0.status', 'RECEIVED')
        ->assertJsonPath('history.1.status', 'SUBMITTED')
        ->assertJsonPath('history.2.status', 'PENDING')
        ->assertJsonPath('history.3.status', 'COMPLETED');
});