<?php

namespace App\Http\Controllers;

use App\Enums\SyncStatus;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            // nullable: an empty ?sync_status= means "no filter".
            'sync_status' => ['sometimes', 'nullable', Rule::enum(SyncStatus::class)],
        ]);

        $orders = Order::query()
            ->when($validated['sync_status'] ?? null, fn ($query, $status) => $query->where('sync_status', $status))
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            // Keep the status filter in pagination links.
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return $this->detail($order);
    }

    /**
     * Send or retry the order. A failed attempt is a normal outcome and
     * returns 200 like a successful one (task 1.3, D5).
     */
    public function sync(Order $order, OrderSyncService $syncService): OrderResource
    {
        return $this->detail($syncService->sync($order));
    }

    private function detail(Order $order): OrderResource
    {
        return new OrderResource($order->load([
            'simulatedFailure',
            // The id tie-breaker keeps the order stable when timestamps are equal.
            'syncAttempts' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
        ]));
    }
}
