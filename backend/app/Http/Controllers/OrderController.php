<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderSyncService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    private const PER_PAGE = 15;

    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->when($request->syncStatus(), fn ($query, $status) => $query->where('sync_status', $status))
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
