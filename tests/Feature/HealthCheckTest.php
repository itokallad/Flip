<?php

use Illuminate\Support\Facades\DB;

test('health endpoint returns ok status', function () {
    $response = $this->get('/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
        ]);
});

test('health endpoint returns 500 when database fails', function () {
    DB::shouldReceive('connection->getPdo')
        ->once()
        ->andThrow(new \Exception('Connection refused'));

    $response = $this->get('/health');

    $response->assertStatus(500)
        ->assertJson([
            'status' => 'error',
            'message' => 'Database connection failed',
        ]);
});
