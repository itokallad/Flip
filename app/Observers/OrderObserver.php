<?php

namespace App\Observers;

use App\Jobs\ProcessOrderJob;
use App\Models\Order;

class OrderObserver
{
    public function saved(Order $order): void
    {
        if ($order->isDirty('status') || $order->wasChanged('status') || $order->wasRecentlyCreated) {
            $order->transitions()->create([
                'status' => $order->status,
            ]);

            ProcessOrderJob::dispatch($order);
        }
    }

}