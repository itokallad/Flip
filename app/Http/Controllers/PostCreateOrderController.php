<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class PostCreateOrderController extends Controller
{
    public function __invoke(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->addLogs($validated['orderId']);

        $order = Order::firstOrCreate(
        ['id' => $validated['orderId']],
        ['status' => 'RECEIVED']
        );

        $statusCode = $this->getStatusCode($order);

        return response()->json([
            'orderId' => $order->id,
            'status' => $order->status,
        ], $statusCode);
    }

    private function getStatusCode(Order $order): int
    {
        if ($order->wasRecentlyCreated) {
            return 201;
        }

        return 200;
    }

    private function addLogs(string $orderId): void
    {
        if (!\Illuminate\Support\Facades\Context::has('correlation_id')) {
            \Illuminate\Support\Facades\Context::add('correlation_id', (string)\Illuminate\Support\Str::uuid());
        }

        \Illuminate\Support\Facades\Log::info('Received create order request.', [
            'orderId' => $orderId,
        ]);
    }
}