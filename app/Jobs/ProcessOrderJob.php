<?php

namespace App\Jobs;

use App\Models\Order;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOrderJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = 3;

    public function __construct(
        public Order $order,
        )
    {
    }

    public function handle(): void
    {
        $context = [
            'orderId' => $this->order->id,
            'retry_attempt' => $this->attempts(),
        ];

        Log::info('Starting to process order', $context);

        if ($this->order->status === 'RECEIVED') {
            $this->changeStatus('SUBMITTED');
        }

        if ($this->order->status === 'SUBMITTED') {
            $start = microtime(true);
            $response = Http::post(config('app.url') . '/api/provider/submit', [
                'orderId' => $this->order->id,
            ]);
            $timeMs = round((microtime(true) - $start) * 1000, 2);

            Log::info('Provider call: POST /provider/submit', array_merge($context, [
                'provider_call_result' => $response->status(),
                'provider_call_timing_ms' => $timeMs,
            ]));

            if ($response->failed()) {
                throw new Exception("Provider submit returned status {$response->status()}: {$response->body()}");
            }

            $providerOrderId = $response->json('providerOrderId');

            \Illuminate\Support\Facades\Cache::put('order_provider_id_' . $this->order->id, $providerOrderId, 3600);

            $this->changeStatus($response->json('status'));
        }

        while ($this->order->status === 'PENDING') {
            $providerOrderId = \Illuminate\Support\Facades\Cache::get('order_provider_id_' . $this->order->id);

            $start = microtime(true);
            $response = Http::get(config('app.url') . '/api/provider/status/' . $providerOrderId);
            $timeMs = round((microtime(true) - $start) * 1000, 2);

            Log::info("Provider call: GET /provider/status/{$providerOrderId}", array_merge($context, [
                'provider_call_result' => $response->status(),
                'provider_call_timing_ms' => $timeMs,
            ]));

            if ($response->failed()) {
                throw new Exception("Provider status returned status {$response->status()}: {$response->body()}");
            }

            $providerStatus = $response->json('status');

            if ($providerStatus === 'COMPLETED') {
                $this->changeStatus('COMPLETED');
            }
            elseif ($providerStatus === 'FAILED') {
                $this->changeStatus('FAILED');
                break;
            }
            else {
                sleep(2);
            }
        }

        Log::info('Finished processing order', $context);
    }

    private function changeStatus(string $newStatus)
    {
        $oldStatus = $this->order->status;
        $this->order->update(['status' => $newStatus]);

        Log::info('Order status transitioned', [
            'orderId' => $this->order->id,
            'retry_attempt' => $this->attempts(),
            'state_transition' => "{$oldStatus} -> {$newStatus}",
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Order failed', [
            'orderId' => $this->order->id,
            'retry_attempt' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        $this->changeStatus('FAILED');
        $this->order->update(['reason' => $exception->getMessage()]);
    }
}