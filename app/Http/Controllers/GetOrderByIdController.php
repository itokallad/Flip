<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrderByIdController extends Controller
{
    public function __invoke(Request $request, $id): JsonResponse
    {
        $order = Order::with('transitions')->findOrFail($id);

        $history = $order->transitions->map(function ($transition) {
            return [
                'status' => $transition->status,
                'timestamp' => $transition->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => $order->status,
            'history' => $history,
        ]);
    }
}
