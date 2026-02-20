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

        $providerOrderId = Str::uuid()->toString();
        $isFailTest = str_starts_with($orderId, 'fail_');
        $isRetryTest = str_starts_with($orderId, 'retry_');

        Cache::put('provider_submit_'.$orderId, $providerOrderId, now()->addHours(1));

        Cache::put('provider_status_'.$providerOrderId, [
            'timestamp' => now()->timestamp,
            'force_fail' => $isFailTest,
            'force_retry' => $isRetryTest,
            'status_check_count' => 0,
        ], now()->addHours(1));

        return response()->json([
            'providerOrderId' => $providerOrderId,
            'status' => 'PENDING',
        ]);
    }

    public function status(Request $request, $providerOrderId)
    {
        $statusData = Cache::get('provider_status_'.$providerOrderId);

        if (! $statusData) {
            return response()->json(['error' => 'Provider order not found'], 404);
        }

        $submittedAt = is_array($statusData) ? $statusData['timestamp'] : $statusData;
        $forceFail = is_array($statusData) ? ($statusData['force_fail'] ?? false) : false;
        $forceRetry = is_array($statusData) ? ($statusData['force_retry'] ?? false) : false;
        $checkCount = is_array($statusData) ? ($statusData['status_check_count'] ?? 0) : 0;

        $checkCount++;

        if (is_array($statusData)) {
            $statusData['status_check_count'] = $checkCount;
            Cache::put('provider_status_'.$providerOrderId, $statusData, now()->addHours(1));
        }

        if ($forceRetry && $checkCount <= 2) {
            return response()->json([
                'error' => 'Internal Provider Error',
                'message' => 'Simulated deterministic 500 error for retry testing.',
            ], 500);
        }

        $elapsedSeconds = now()->timestamp - $submittedAt;

        if ($elapsedSeconds < 2) {
            return response()->json([
                'providerOrderId' => $providerOrderId,
                'status' => 'PENDING',
            ]);
        }

        if ($forceFail) {
            $finalStatus = 'FAILED';
        } else {
            $finalStatus = 'COMPLETED';
        }

        return response()->json([
            'providerOrderId' => $providerOrderId,
            'status' => $finalStatus,
        ]);
    }
}
