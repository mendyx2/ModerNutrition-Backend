<?php

namespace App\Jobs;

use App\Commerce\Services\CommerceAllocationEngine;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOrderCvAllocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    public function __construct(public Order $order)
    {
        $this->onQueue('allocations');
        $this->onConnection('redis');
    }

    public function handle(CommerceAllocationEngine $engine): void
    {
        Log::info("Starting queued CV allocation for Order #{$this->order->order_number} on Redis queue...");

        $entries = $engine->allocate($this->order);

        Log::info("Completed queued CV allocation for Order #{$this->order->order_number}. Created " . count($entries) . " ledger rows.");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Failed CV allocation for Order #{$this->order->order_number}: " . $exception->getMessage(), [
            'order_id' => $this->order->id,
            'trace'    => $exception->getTraceAsString(),
        ]);
    }
}
