<?php

use Illuminate\Support\Facades\Cache;

test('provider simulation submit endpoint', function () {
    $response = $this->postJson('/api/provider/submit', [
        'orderId' => 'test_order_1',
    ]);

    if ($response->status() === 500) {
        $response->assertJsonStructure(['error', 'message']);
    } else {
        $response->assertStatus(200)
            ->assertJsonStructure(['providerOrderId', 'status'])
            ->assertJsonPath('status', 'PENDING');

        $providerOrderId = $response->json('providerOrderId');

        $response2 = $this->postJson('/api/provider/submit', [
            'orderId' => 'test_order_1',
        ]);
        $response2->assertStatus(200)
            ->assertJsonPath('providerOrderId', $providerOrderId);
    }
});

test('provider simulation status endpoint', function () {

    Cache::put('provider_status_test_id', now()->timestamp);

    $response = $this->getJson('/api/provider/status/test_id');

    if ($response->status() === 500) {
        $response->assertJsonStructure(['error', 'message']);
    } else {
        $response->assertStatus(200)
            ->assertJsonStructure(['providerOrderId', 'status'])
            ->assertJsonPath('status', 'PENDING');
    }
});
