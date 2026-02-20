<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProviderSimulationController extends Controller
{
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'required|string',
        ]);

        $orderId = $validated['orderId'];

        $cachedProviderOrderId = Cache::get('provider_submit_'.$orderId);
        if ($cachedProviderOrderId) {
            return response()->json([
                'providerOrderId' => $cachedProviderOrderId,
                'status' => 'PENDING',
                'message' => 'Returning cached response (Idempotency)',
            ]);
        }

        if (rand(1, 100) <= 20) {
            usleep(rand(2000000, 3000000));
        } else {
            usleep(rand(100000, 500000));
        }

        if (rand(1, 100) <= 20) {
            return response()->json([
                'error' => 'Internal Provider Error',
                'message' => 'Simulated 500 error.',
            ], 500);
        }

        $providerOrderId = Str::uuid()->toString();

        Cache::put('provider_submit_'.$orderId, $providerOrderId, now()->addHours(1));

        Cache::put('provider_status_'.$providerOrderId, now()->timestamp, now()->addHours(1));

        return response()->json([
            'providerOrderId' => $providerOrderId,
            'status' => 'PENDING',
        ]);
    }

    public function status(Request $request, $providerOrderId)
    {
        $submittedAt = Cache::get('provider_status_'.$providerOrderId);

        if (! $submittedAt) {
            return response()->json(['error' => 'Provider order not found'], 404);
        }

        $elapsedSeconds = now()->timestamp - $submittedAt;

        if (rand(1, 100) <= 10) {
            return response()->json([
                'error' => 'Internal Provider Error',
                'message' => 'Simulated 500 error on status fetch.',
            ], 500);
        }

        if ($elapsedSeconds < rand(2, 5)) {
            return response()->json([
                'providerOrderId' => $providerOrderId,
                'status' => 'PENDING',
            ]);
        }

        $finalStatus = (rand(1, 100) <= 90) ? 'COMPLETED' : 'FAILED';

        return response()->json([
            'providerOrderId' => $providerOrderId,
            'status' => $finalStatus,
        ]);
    }
}
