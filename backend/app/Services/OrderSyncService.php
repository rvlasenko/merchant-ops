<?php

namespace App\Services;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncStatus;
use App\Exceptions\OrderAlreadySyncedException;
use App\ExternalService\FakeExternalService;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The only code that writes sync attempts and the sync summary on orders.
 * The attempt and the summary are written in one transaction, so they never
 * disagree (task 1.3, D2).
 */
class OrderSyncService
{
    public function __construct(private FakeExternalService $externalService) {}

    /**
     * Send the order to the external service and record the outcome.
     *
     * Returns a fresh instance; the passed one is not updated.
     *
     * @throws OrderAlreadySyncedException
     */
    public function sync(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Concurrent syncs of the same order wait here and then see the state left by the first one.
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->sync_status === SyncStatus::Synced) {
                throw new OrderAlreadySyncedException;
            }

            $trigger = $locked->attempts_count === 0 ? AttemptTrigger::Initial : AttemptTrigger::Retry;
            $response = $this->externalService->send($locked);
            $code = $response->errorCode;

            // The attempt is inserted before the order is updated.
            $attempt = $locked->syncAttempts()->create([
                'trigger' => $trigger,
                'result' => $response->isSuccess() ? AttemptResult::Success : AttemptResult::Failed,
                'error_code' => $code,
                'error_message' => $code?->message(),
                'retryable' => $code?->retryable(),
            ]);

            // Saved through Eloquent, not a query-builder update: model events must fire.
            $locked->fill([
                'sync_status' => $response->isSuccess() ? SyncStatus::Synced : SyncStatus::Failed,
                'external_order_id' => $response->externalOrderId,
                'last_error_code' => $code,
                'attempts_count' => $locked->attempts_count + 1,
                // One timestamp per attempt.
                'last_attempt_at' => $attempt->created_at,
            ])->save();

            return $locked;
        });
    }
}
